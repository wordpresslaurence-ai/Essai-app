<?php

namespace App\Services\Whatsapp;

/**
 * Lit une discussion WhatsApp exportée (menu « Exporter la discussion », sans
 * médias) ou copiée telle quelle. 100 % local : le CRM ne se connecte pas à
 * WhatsApp. Formats reconnus :
 *   Android : 13/12/2026, 10:32 - Marie Durand: Bonjour
 *   iPhone  : [13/12/2026 10:32:05] Marie Durand: Bonjour
 * Les lignes sans date prolongent le message précédent.
 */
class LecteurDiscussion
{
    private const DEBUT = '/^\[?(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}),?\s+(?:à\s+)?(\d{1,2}[:.h]\d{2})(?:[:.]\d{2})?(\s?[AaPp]\.?\s?[Mm]\.?)?\]?\s*(?:[-–]\s*)?(.*)$/u';

    /**
     * @return array<int, array{date: string, heure: string, auteur: string, texte: string}>
     */
    public function lire(string $contenu): array
    {
        // Caractères invisibles ajoutés par WhatsApp, espaces insécables, fins de ligne.
        $contenu = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $contenu) ?? $contenu;
        $contenu = str_replace(["\u{202F}", "\u{00A0}", "\r\n", "\r"], [' ', ' ', "\n", "\n"], $contenu);

        $messages = [];
        $courant = null;

        foreach (explode("\n", $contenu) as $ligne) {
            if (preg_match(self::DEBUT, trim($ligne), $m)) {
                if ($courant) {
                    $messages[] = $courant;
                }
                $courant = null;

                // « Auteur: texte » ; sans deux-points, c'est un message système (ignoré).
                if (preg_match('/^([^:]{1,80}):\s?(.*)$/us', $m[4], $am)) {
                    $courant = [
                        'date' => $m[1],
                        'heure' => str_replace(['.', 'h'], ':', $m[2]).(isset($m[3]) ? strtoupper(preg_replace('/[\s.]/', '', $m[3])) : ''),
                        'auteur' => trim($am[1]),
                        'texte' => trim($am[2]),
                    ];
                }

                continue;
            }

            if ($courant && trim($ligne) !== '') {
                $courant['texte'] = trim($courant['texte']."\n".rtrim($ligne));
            }
        }

        if ($courant) {
            $messages[] = $courant;
        }

        return array_values(array_filter($messages, fn (array $msg) => $msg['texte'] !== ''));
    }

    /** Empreinte d'un message, pour ne pas le garder deux fois. */
    public static function cle(array $message): string
    {
        return md5($message['date'].'|'.$message['heure'].'|'.mb_strtolower($message['auteur']).'|'.preg_replace('/\s+/u', ' ', $message['texte']));
    }

    /** Une ligne lisible, que lire() sait relire (pour comparer avec un import ultérieur). */
    public static function formater(array $message): string
    {
        return '['.$message['date'].' '.$message['heure'].'] '.$message['auteur'].': '.$message['texte'];
    }

    /**
     * Regroupe les messages en blocs de taille raisonnable (une note par bloc).
     *
     * @param  array<int, array{date: string, heure: string, auteur: string, texte: string}>  $messages
     * @return array<int, array<int, array{date: string, heure: string, auteur: string, texte: string}>>
     */
    public static function blocs(array $messages, int $tailleMax = 12000): array
    {
        $blocs = [];
        $bloc = [];
        $taille = 0;

        foreach ($messages as $message) {
            $longueur = mb_strlen(self::formater($message)) + 1;
            if ($bloc && $taille + $longueur > $tailleMax) {
                $blocs[] = $bloc;
                $bloc = [];
                $taille = 0;
            }
            $bloc[] = $message;
            $taille += $longueur;
        }

        if ($bloc) {
            $blocs[] = $bloc;
        }

        return $blocs;
    }
}
