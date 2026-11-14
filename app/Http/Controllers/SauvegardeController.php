<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\AdresseEmail;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Etiquette;
use App\Models\Inscription;
use App\Models\Opportunite;
use App\Models\Pole;
use App\Models\Seance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Sauvegarde téléchargeable des DONNÉES MÉTIER (contacts, opportunités,
 * activités, e-mails, sessions…). Volontairement SANS secrets : ni la table
 * `parametres` (clés API, mots de passe chiffrés), ni `users`, ni `boites_email`
 * (mot de passe de boîte) ne sont exportés. Ce n'est pas un dump SQL complet
 * (phpMyAdmin / Hostinger restent la référence pour une restauration), mais un
 * filet pratique que l'utilisatrice garde chez elle.
 */
class SauvegardeController extends Controller
{
    /**
     * Les données exportées, table par table. Aucune table contenant un secret.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function donnees(): array
    {
        return [
            'poles' => Pole::all()->toArray(),
            'categories' => Categorie::all()->toArray(),
            'contacts' => Contact::all()->toArray(),
            'adresses_email' => AdresseEmail::all()->toArray(),
            'etiquettes' => Etiquette::all()->toArray(),
            'contact_etiquette' => DB::table('contact_etiquette')->get()->map(fn ($r) => (array) $r)->all(),
            'opportunites' => Opportunite::all()->toArray(),
            'activites' => Activite::all()->toArray(),
            'emails' => Email::all()->toArray(),
            'seances' => Seance::all()->toArray(),
            'inscriptions' => Inscription::all()->toArray(),
            'seance_dates' => \App\Models\SeanceDate::all()->toArray(),
            'presences_date' => \App\Models\PresenceDate::all()->toArray(),
            'messages_telegram' => \App\Models\MessageTelegram::all()->toArray(),
            'paiements' => \App\Models\Paiement::all()->toArray(),
            'radars_site' => \App\Models\RadarSite::all()->toArray(),
            'invitations' => \App\Models\Invitation::all()->toArray(),
            'modeles_message' => \App\Models\ModeleMessage::all()->toArray(),
        ];
    }

    public function telecharger(): StreamedResponse
    {
        $donnees = $this->donnees();
        $horodatage = Carbon::now()->format('Y-m-d_H\hi');

        // Sans l'extension ZIP (rare), on se replie sur un JSON complet unique.
        if (! class_exists(ZipArchive::class)) {
            $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return response()->streamDownload(
                fn () => print($json),
                "sauvegarde-crm-$horodatage.json",
                ['Content-Type' => 'application/json'],
            );
        }

        $chemin = tempnam(sys_get_temp_dir(), 'crm-sauvegarde').'.zip';
        $zip = new ZipArchive;
        $zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Un CSV lisible par table + un JSON complet (pour un éventuel réimport).
        foreach ($donnees as $table => $lignes) {
            $zip->addFromString("csv/$table.csv", $this->versCsv($lignes));
        }
        $zip->addFromString('sauvegarde-complete.json', (string) json_encode(
            $donnees,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
        $zip->addFromString('LISEZMOI.txt', $this->lisezMoi());
        $zip->close();

        return response()->streamDownload(function () use ($chemin) {
            readfile($chemin);
            @unlink($chemin);
        }, "sauvegarde-crm-$horodatage.zip", ['Content-Type' => 'application/zip']);
    }

    /**
     * Transforme des lignes (tableaux associatifs) en CSV UTF-8 (séparateur « ; »,
     * compatible Excel FR). Les valeurs tableau/objet (colonnes JSON) sont
     * sérialisées en JSON dans la cellule.
     *
     * @param  array<int, array<string, mixed>>  $lignes
     */
    private function versCsv(array $lignes): string
    {
        if ($lignes === []) {
            return '';
        }

        $flux = fopen('php://temp', 'r+');
        fputcsv($flux, array_keys($lignes[0]), ';');

        foreach ($lignes as $ligne) {
            $cellules = array_map(
                fn ($v) => is_array($v) || is_object($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v,
                $ligne,
            );
            fputcsv($flux, $cellules, ';');
        }

        rewind($flux);
        $csv = (string) stream_get_contents($flux);
        fclose($flux);

        // BOM UTF-8 pour qu'Excel affiche correctement les accents.
        return "\u{FEFF}".$csv;
    }

    private function lisezMoi(): string
    {
        return <<<TXT
        Sauvegarde du CRM « Laurence B. » — {$this->maintenant()}

        Ce dossier contient tes DONNÉES (contacts, opportunités, activités, e-mails,
        sessions, étiquettes…) :
          - dossier csv/ : un fichier par type de donnée, ouvrable dans Excel/LibreOffice ;
          - sauvegarde-complete.json : toutes les données en un seul fichier.

        Ce qui N'EST PAS inclus (volontairement) : les mots de passe, les clés de
        l'assistant IA et les réglages de boîte e-mail (données sensibles).

        Pour une sauvegarde COMPLÈTE et restaurable de la base, utilise aussi
        l'export SQL via phpMyAdmin (hPanel Hostinger) ou les sauvegardes Hostinger.

        Garde ce fichier en lieu sûr (ton ordinateur + un cloud ou un disque externe).
        TXT;
    }

    private function maintenant(): string
    {
        return Carbon::now()->format('d/m/Y H:i');
    }
}
