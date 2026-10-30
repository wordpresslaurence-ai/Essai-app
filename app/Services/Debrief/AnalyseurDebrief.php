<?php

namespace App\Services\Debrief;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Analyse le texte d'un débrief d'appel collé (sortie conversationnelle de
 * ChatGPT au format fixe) et en extrait les champs exploitables par le CRM.
 * 100 % local, sans IA ni réseau : le travail « intelligent » a été fait par
 * ChatGPT côté utilisateur ; ici on ne fait que lire un format connu.
 */
class AnalyseurDebrief
{
    /** Correspondance étiquette (normalisée) → champ interne. */
    private const ETIQUETTES = [
        'TYPE' => 'type',
        'RESUME' => 'resume',
        'CAPACITE' => 'capacite',
        'TARIF' => 'tarif',
        'DISPONIBILITES' => 'disponibilites',
        'BESOINS' => 'besoins',           // « BESOINS / ATTENTES »
        'ATTENTES' => 'besoins',
        'OBJECTIONS' => 'objections',     // « OBJECTIONS / FREINS »
        'FREINS' => 'objections',
        'PROMIS' => 'promis',             // « PROMIS / DÉCIDÉ »
        'DECIDE' => 'promis',
        'PROCHAINE ACTION' => 'prochaine_action',
        'ECHEANCE' => 'echeance',
    ];

    /** Types d'appel reconnus. */
    private const TYPES = ['prospection_salle', 'formation_eleve', 'partenariat', 'autre'];

    public function analyser(string $texte): ResultatDebrief
    {
        $texteComplet = $this->nettoyerTexteComplet($texte);

        $champs = [];
        $courant = null;

        foreach (preg_split('/\r\n|\r|\n/', $texte) as $ligne) {
            $brut = trim($ligne);
            if ($brut === '' || $this->estMarqueur($brut)) {
                continue;
            }

            // Une puce « - Capacité : … » est traitée comme une étiquette.
            $sansPuce = ltrim($brut, "-•* \t");

            [$champ, $valeur] = $this->detecterEtiquette($sansPuce);

            if ($champ !== null) {
                $courant = $champ;
                // Un en-tête de section (ex. « POINTS CLÉS : ») sans champ propre
                // sert juste à ouvrir la liste : on n'accumule rien dessus.
                if ($champ === '') {
                    $courant = null;

                    continue;
                }
                $champs[$champ] = isset($champs[$champ]) && $champs[$champ] !== ''
                    ? $champs[$champ]."\n".$valeur
                    : $valeur;

                continue;
            }

            // Ligne de continuation du dernier champ (valeur multi-lignes).
            if ($courant !== null) {
                $champs[$courant] = ($champs[$courant] ?? '')."\n".$brut;
            }
        }

        return new ResultatDebrief(
            type: $this->normaliserType($this->valeur($champs, 'type')),
            resume: $this->valeur($champs, 'resume'),
            capacite: $this->extraireEntier($this->valeur($champs, 'capacite')),
            tarif: $this->valeur($champs, 'tarif'),
            disponibilites: $this->valeur($champs, 'disponibilites'),
            besoins: $this->valeur($champs, 'besoins'),
            objections: $this->valeur($champs, 'objections'),
            promis: $this->valeur($champs, 'promis'),
            prochaineAction: $this->valeur($champs, 'prochaine_action'),
            echeance: $this->parserEcheance($this->valeur($champs, 'echeance')),
            texteComplet: $texteComplet,
        );
    }

    /** Détecte « ÉTIQUETTE : valeur ». Renvoie [champ|null|'', valeur]. */
    private function detecterEtiquette(string $ligne): array
    {
        $pos = mb_strpos($ligne, ':');
        if ($pos === false) {
            return [null, ''];
        }

        $label = $this->normaliser(mb_substr($ligne, 0, $pos));
        $valeur = trim(mb_substr($ligne, $pos + 1));

        // En-tête de section « POINTS CLÉS » ou ligne « Autres faits » : pas de
        // champ structuré propre (le détail reste dans le texte complet de la note).
        if (str_starts_with($label, 'POINTS CLES') || str_starts_with($label, 'AUTRES')) {
            return ['', ''];
        }

        foreach (self::ETIQUETTES as $prefixe => $champ) {
            if (str_starts_with($label, $prefixe)) {
                return [$champ, $valeur];
            }
        }

        return [null, ''];
    }

    private function estMarqueur(string $ligne): bool
    {
        return str_starts_with($ligne, '===') || str_starts_with($ligne, '---');
    }

    /** Majuscules, sans accents, espaces compactés. */
    private function normaliser(string $s): string
    {
        $s = Str::ascii($s);

        return trim(preg_replace('/\s+/', ' ', mb_strtoupper($s)));
    }

    /** Valeur d'un champ, ou null si vide / placeholder. */
    private function valeur(array $champs, string $champ): ?string
    {
        $v = trim((string) ($champs[$champ] ?? ''));
        $v = trim($v, "-•* \t\n");

        if ($v === '') {
            return null;
        }

        // Placeholders fréquents laissés par l'IA.
        $norm = $this->normaliser($v);
        if (in_array($norm, ['N/A', 'NA', 'VIDE', 'AUCUN', 'AUCUNE', 'INCONNU', 'INCONNUE', 'RAS'], true)) {
            return null;
        }
        if (str_starts_with($v, '(') && str_ends_with($v, ')')) {
            return null;
        }

        return $v;
    }

    private function normaliserType(?string $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $cle = str_replace([' ', '-', "'"], ['_', '_', ''], mb_strtolower(Str::ascii($valeur)));
        $cle = trim($cle, '_');

        return in_array($cle, self::TYPES, true) ? $cle : 'autre';
    }

    private function extraireEntier(?string $valeur): ?int
    {
        if ($valeur === null) {
            return null;
        }

        return preg_match('/\d+/', str_replace([' ', '.', "\u{00A0}"], '', $valeur), $m)
            ? (int) $m[0]
            : null;
    }

    /** Parse « AAAA-MM-JJ » ou « dans X jours/semaines/mois ». */
    private function parserEcheance(?string $valeur): ?Carbon
    {
        if ($valeur === null) {
            return null;
        }

        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $valeur, $m)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $m[0])->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/dans\s+(\d+)\s*(jour|semaine|mois)/iu', $valeur, $m)) {
            $n = (int) $m[1];

            return match (mb_strtolower($m[2])) {
                'jour' => Carbon::now()->addDays($n)->startOfDay(),
                'semaine' => Carbon::now()->addWeeks($n)->startOfDay(),
                'mois' => Carbon::now()->addMonths($n)->startOfDay(),
                default => null,
            };
        }

        return null;
    }

    /** Retire les marqueurs d'encadrement, pour garder un texte de note lisible. */
    private function nettoyerTexteComplet(string $texte): string
    {
        $lignes = array_filter(
            preg_split('/\r\n|\r|\n/', trim($texte)),
            fn ($l) => ! $this->estMarqueur(trim($l)),
        );

        return trim(implode("\n", $lignes));
    }
}
