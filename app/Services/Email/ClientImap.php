<?php

namespace App\Services\Email;

use App\Models\BoiteEmail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Lecture d'une boîte e-mail via IMAP (extension PHP native « imap »), en lecture
 * seule. Le serveur, le port et les dossiers viennent de la boîte (Gmail, Hostinger
 * ou autre) ; les identifiants sont chiffrés. Le vrai échange réseau n'est exercé
 * qu'au jalon de test réel ; en test automatique on utilise LecteurFactice.
 */
class ClientImap implements LecteurEmails
{
    public function tester(BoiteEmail $boite): bool
    {
        $this->verifierExtension();
        $this->verifierIdentifiants($boite);

        $flux = @imap_open($boite->chaineImap('INBOX'), $boite->email, (string) $boite->secret(), OP_READONLY, 1);

        if ($flux === false) {
            throw new RuntimeException($this->dernierMessageErreur());
        }

        imap_close($flux);

        return true;
    }

    public function recupererDepuis(BoiteEmail $boite, ?CarbonInterface $depuis): array
    {
        $this->verifierExtension();
        $this->verifierIdentifiants($boite);

        // Borne : la date fournie, sinon 90 jours en arrière.
        $borne = $depuis ?? Carbon::now()->subDays(90);
        $critere = 'SINCE "'.$borne->copy()->subDay()->format('d M Y').'"';

        $messages = [];

        foreach ($boite->dossiers() as $dossier) {
            $flux = @imap_open($boite->chaineImap($dossier), $boite->email, (string) $boite->secret(), OP_READONLY, 1);
            if ($flux === false) {
                continue; // un dossier absent ne doit pas tout bloquer
            }

            $ids = imap_search($flux, $critere, SE_UID) ?: [];
            foreach ($ids as $uid) {
                $entete = imap_rfc822_parse_headers(imap_fetchheader($flux, $uid, FT_UID));
                $message = $this->construireMessage($flux, $uid, $entete);
                if ($message !== null) {
                    $messages[] = $message;
                }
            }

            imap_close($flux);
        }

        return $messages;
    }

    private function construireMessage(\IMAP\Connection|false $flux, int $uid, object $entete): ?MessageEmail
    {
        $messageId = isset($entete->message_id) ? trim($entete->message_id) : '';
        if ($messageId === '') {
            return null;
        }

        $de = $this->premiereAdresse($entete->from ?? []);
        if ($de === null) {
            return null;
        }

        $corps = $this->extraireCorps($flux, $uid);

        return new MessageEmail(
            messageId: $messageId,
            expediteur: $de,
            destinataires: $this->toutesAdresses($entete->to ?? []),
            objet: isset($entete->subject) ? $this->decoder($entete->subject) : null,
            texte: $corps,
            date: isset($entete->date) ? Carbon::parse($entete->date) : Carbon::now(),
            filId: isset($entete->references) ? trim((string) $entete->references) : $messageId,
        );
    }

    /** @param array<object> $adresses */
    private function premiereAdresse(array $adresses): ?string
    {
        foreach ($adresses as $a) {
            if (isset($a->mailbox, $a->host)) {
                return strtolower($a->mailbox.'@'.$a->host);
            }
        }

        return null;
    }

    /**
     * @param  array<object>  $adresses
     * @return list<string>
     */
    private function toutesAdresses(array $adresses): array
    {
        $out = [];
        foreach ($adresses as $a) {
            if (isset($a->mailbox, $a->host)) {
                $out[] = strtolower($a->mailbox.'@'.$a->host);
            }
        }

        return $out;
    }

    /**
     * Décode un en-tête MIME (objet) en texte UTF-8. Chaque morceau est converti
     * depuis sa charset déclarée (ISO-8859-1, Windows-1252…) : sans cela, un « é »
     * arrive en octet brut et MySQL (utf8mb4) refuse l'insertion.
     */
    private function decoder(string $valeur): string
    {
        $texte = '';

        foreach (imap_mime_header_decode($valeur) as $p) {
            $texte .= $this->versUtf8($p->text, $p->charset ?? 'default');
        }

        return $this->versUtf8($texte); // filet de sécurité
    }

    /**
     * Extrait le corps texte d'un message en UTF-8 propre : on repère la partie
     * text/plain, on défait l'encodage de transfert (base64 / quoted-printable)
     * puis on convertit depuis la charset du message. Tout est gardé défensif :
     * en cas de doute on renvoie la première section, jamais d'exception.
     */
    private function extraireCorps(\IMAP\Connection|false $flux, int $uid): ?string
    {
        $structure = @imap_fetchstructure($flux, $uid, FT_UID);

        if ($structure === false) {
            $brut = (string) @imap_fetchbody($flux, $uid, '1', FT_UID | FT_PEEK);
            $corps = trim($this->versUtf8($brut));

            return $corps !== '' ? $corps : null;
        }

        [$section, $encoding, $charset] = $this->sectionTexte($structure);

        $brut = (string) @imap_fetchbody($flux, $uid, $section, FT_UID | FT_PEEK);

        $decode = match ($encoding) {
            3 => (string) base64_decode($brut, false),   // BASE64
            4 => quoted_printable_decode($brut),          // QUOTED-PRINTABLE
            default => $brut,
        };

        $corps = trim($this->versUtf8($decode, $charset));

        return $corps !== '' ? $corps : null;
    }

    /**
     * Localise la section text/plain (sinon la 1ʳᵉ) et renvoie
     * [numéro de section, encodage de transfert, charset].
     *
     * @return array{0: string, 1: int, 2: string}
     */
    private function sectionTexte(object $structure): array
    {
        // Message simple (non multipart) : une seule section « 1 ».
        if (empty($structure->parts)) {
            return ['1', (int) ($structure->encoding ?? 0), $this->charsetDe($structure)];
        }

        $repli = null;

        foreach ($structure->parts as $i => $part) {
            $num = (string) ($i + 1);

            // Partie imbriquée (multipart/alternative) : on descend d'un niveau.
            if (! empty($part->parts)) {
                foreach ($part->parts as $j => $sous) {
                    if ($this->estTextePlain($sous)) {
                        return [$num.'.'.($j + 1), (int) ($sous->encoding ?? 0), $this->charsetDe($sous)];
                    }
                }

                continue;
            }

            if ($this->estTextePlain($part)) {
                return [$num, (int) ($part->encoding ?? 0), $this->charsetDe($part)];
            }

            $repli ??= [$num, (int) ($part->encoding ?? 0), $this->charsetDe($part)];
        }

        return $repli ?? ['1', (int) ($structure->encoding ?? 0), $this->charsetDe($structure)];
    }

    /** Vrai si la partie est du text/plain (type 0 = text, sous-type PLAIN). */
    private function estTextePlain(object $part): bool
    {
        return (int) ($part->type ?? 0) === 0
            && strtoupper((string) ($part->subtype ?? '')) === 'PLAIN';
    }

    /** Charset déclarée d'une partie MIME (défaut : UTF-8). */
    private function charsetDe(object $part): string
    {
        foreach (($part->parameters ?? []) as $p) {
            if (strtoupper((string) ($p->attribute ?? '')) === 'CHARSET') {
                return (string) $p->value;
            }
        }

        return 'UTF-8';
    }

    /**
     * Convertit une chaîne en UTF-8 valide. Si la charset est connue on l'utilise ;
     * sinon, pour des octets non-UTF-8 (souvent latin-1 / Windows-1252), on replie
     * sur Windows-1252 — surensemble de l'ISO-8859-1 couvrant les mails courants.
     */
    private function versUtf8(string $valeur, string $charset = 'default'): string
    {
        if ($valeur === '') {
            return '';
        }

        $charset = strtoupper($charset);

        if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && $charset !== 'US-ASCII' && function_exists('mb_convert_encoding')) {
            $converti = @mb_convert_encoding($valeur, 'UTF-8', $charset);
            if (is_string($converti) && $converti !== '') {
                return $converti;
            }
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($valeur, 'UTF-8')) {
            return $valeur;
        }

        $converti = function_exists('mb_convert_encoding')
            ? @mb_convert_encoding($valeur, 'UTF-8', 'Windows-1252')
            : null;

        return is_string($converti) && $converti !== '' ? $converti : $valeur;
    }

    private function verifierExtension(): void
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException("L'extension IMAP de PHP n'est pas disponible sur l'hébergement.");
        }
    }

    private function verifierIdentifiants(BoiteEmail $boite): void
    {
        if (blank($boite->email) || blank($boite->secret())) {
            throw new RuntimeException("Renseigne d'abord l'adresse et le mot de passe de la boîte « ".$boite->nomAffichage().' ».');
        }
    }

    private function dernierMessageErreur(): string
    {
        $erreurs = imap_errors() ?: [];

        return self::interpreterErreur((string) (end($erreurs) ?: ''));
    }

    /**
     * Traduit une erreur IMAP brute en message clair : identifiants refusés
     * (mauvais fournisseur / mot de passe non retapé) vs serveur injoignable
     * (hôte, port, réseau, certificat). Statique et pure pour être testable
     * sans l'extension IMAP.
     */
    public static function interpreterErreur(string $dernier): string
    {
        if ($dernier === '') {
            return 'Connexion impossible : vérifie le fournisseur, l’adresse et le mot de passe.';
        }

        $bas = strtolower($dernier);

        // Le mot de passe (ou l'adresse) est refusé par le serveur : souvent un
        // mot de passe non retapé, ou le mauvais fournisseur (donc le mauvais
        // serveur) pour cette adresse.
        if (str_contains($bas, 'authenticationfailed')
            || str_contains($bas, 'invalid credentials')
            || str_contains($bas, 'authentication failed')
            || str_contains($bas, 'login failure')
            || str_contains($bas, 'authentication')) {
            return 'Identifiants refusés par le serveur. Vérifie que le fournisseur est le bon '
                .'(Gmail vs Hostinger), puis retape le mot de passe de la boîte. Détail : '.$dernier;
        }

        // On n'a pas pu joindre le serveur (hôte/port/réseau/certificat).
        if (str_contains($bas, 'can not connect')
            || str_contains($bas, 'cannot connect')
            || str_contains($bas, 'connection refused')
            || str_contains($bas, 'timed out')
            || str_contains($bas, 'host not found')
            || str_contains($bas, 'no route')
            || str_contains($bas, 'name or service not known')
            || str_contains($bas, 'certificate')
            || str_contains($bas, 'ssl')) {
            return 'Serveur injoignable : vérifie le fournisseur et, en mode « Autre », '
                .'le serveur IMAP et le port. Détail : '.$dernier;
        }

        return 'Connexion refusée : '.$dernier;
    }
}
