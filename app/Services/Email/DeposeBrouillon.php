<?php

namespace App\Services\Email;

use App\Models\BoiteEmail;
use RuntimeException;

/**
 * Dépose un e-mail dans le dossier « Brouillons » d'une boîte (IMAP APPEND).
 * Rien n'est envoyé : le brouillon apparaît dans la messagerie (webmail, téléphone,
 * logiciel), où on le relit et on l'envoie soi-même. Les liens sont cliquables.
 */
class DeposeBrouillon
{
    /** Retourne le nom du dossier où le brouillon a été déposé. */
    public function deposer(BoiteEmail $boite, string $destinataire, string $objet, string $corps): string
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException("L'extension IMAP de PHP n'est pas disponible sur l'hébergement.");
        }
        if (blank($boite->email) || blank($boite->secret())) {
            throw new RuntimeException('Renseigne d’abord l’adresse et le mot de passe de la boîte « '.$boite->nomAffichage().' ».');
        }

        $flux = @imap_open($boite->chaineImap('INBOX'), $boite->email, (string) $boite->secret(), 0, 1);
        if ($flux === false) {
            $erreurs = imap_errors() ?: [];

            throw new RuntimeException('Connexion à la boîte impossible : '.(end($erreurs) ?: 'raison inconnue').'.');
        }

        $message = self::construireMessage($boite->email, $destinataire, $objet, $corps);

        try {
            foreach ($this->dossiersCandidats($flux, $boite) as $dossier) {
                if (@imap_append($flux, $boite->chaineImap($dossier), $message, '\\Draft \\Seen')) {
                    return $dossier;
                }
            }
        } finally {
            imap_errors(); // vide la pile d'erreurs des essais infructueux
            @imap_close($flux);
        }

        throw new RuntimeException('Le dossier « Brouillons » de '.$boite->email.' est introuvable.');
    }

    /**
     * Dossiers où chercher les brouillons : ceux dont le nom l'évoque sur le
     * serveur, puis les noms habituels selon le fournisseur.
     *
     * @param  resource|\IMAP\Connection  $flux
     * @return list<string>
     */
    private function dossiersCandidats($flux, BoiteEmail $boite): array
    {
        $racine = '{'.$boite->hoteImap().':'.$boite->portImap().'/imap/ssl/novalidate-cert}';
        $trouves = [];

        foreach (@imap_list($flux, $racine, '*') ?: [] as $nom) {
            $nom = substr($nom, strlen($racine));
            $lisible = function_exists('mb_convert_encoding') ? (@mb_convert_encoding($nom, 'UTF-8', 'UTF7-IMAP') ?: $nom) : $nom;
            if (preg_match('/draft|brouillon/i', $lisible)) {
                $trouves[] = $lisible;
            }
        }

        $habituels = match ($boite->fournisseur) {
            BoiteEmail::FOURNISSEUR_GMAIL => ['[Gmail]/Brouillons', '[Gmail]/Drafts'],
            BoiteEmail::FOURNISSEUR_HOSTINGER => ['INBOX.Drafts', 'Drafts'],
            default => ['Drafts', 'INBOX.Drafts', 'Brouillons'],
        };

        return array_values(array_unique([...$trouves, ...$habituels]));
    }

    /** Message MIME complet : texte brut et version HTML aux liens cliquables. */
    public static function construireMessage(string $de, string $a, string $objet, string $corps): string
    {
        $frontiere = 'crm-'.bin2hex(random_bytes(8));
        $corps = str_replace(["\r\n", "\r"], "\n", $corps);

        $entetes = [
            'From: '.$de,
            'To: '.$a,
            'Subject: '.mb_encode_mimeheader($objet, 'UTF-8', 'B', "\r\n"),
            'Date: '.now()->format('D, d M Y H:i:s O'),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$frontiere.'"',
        ];

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5">'.self::htmlAvecLiens($corps).'</div>';

        return implode("\r\n", $entetes)."\r\n\r\n"
            .'--'.$frontiere."\r\n"
            ."Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            .quoted_printable_encode(str_replace("\n", "\r\n", $corps))."\r\n"
            .'--'.$frontiere."\r\n"
            ."Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            .quoted_printable_encode($html)."\r\n"
            .'--'.$frontiere."--\r\n";
    }

    /**
     * Texte vers HTML, comme « Copier avec liens cliquables » : sur une ligne
     * « Libellé : https://… », le libellé devient le lien et l'adresse longue est masquée.
     */
    public static function htmlAvecLiens(string $texte): string
    {
        $esc = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return implode('<br>', array_map(function (string $ligne) use ($esc) {
            if (! preg_match('/(https?:\/\/\S+)/', $ligne, $m, PREG_OFFSET_CAPTURE)) {
                return $esc($ligne);
            }

            [$url, $position] = $m[1];
            $avant = substr($ligne, 0, $position);
            $apres = substr($ligne, $position + strlen($url));
            preg_match('/^[\s\-–•*]+/u', $avant, $puce);
            $libelle = trim((string) preg_replace(['/^[\s\-–•*]+/u', '/[\s:–-]+$/u'], '', $avant));

            return $esc($puce[0] ?? '').'<a href="'.$esc($url).'">'.$esc($libelle !== '' ? $libelle : $url).'</a>'.$esc($apres);
        }, explode("\n", $texte)));
    }
}
