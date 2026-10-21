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

        $corps = (string) @imap_fetchbody($flux, $uid, '1', FT_UID | FT_PEEK);

        return new MessageEmail(
            messageId: $messageId,
            expediteur: $de,
            destinataires: $this->toutesAdresses($entete->to ?? []),
            objet: isset($entete->subject) ? $this->decoder($entete->subject) : null,
            texte: $corps !== '' ? $corps : null,
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

    private function decoder(string $valeur): string
    {
        $parts = imap_mime_header_decode($valeur);

        return implode('', array_map(fn ($p) => $p->text, $parts));
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
        $dernier = end($erreurs);

        return $dernier
            ? 'Connexion refusée : '.$dernier
            : 'Connexion à la boîte e-mail impossible (vérifie l’adresse et le mot de passe).';
    }
}
