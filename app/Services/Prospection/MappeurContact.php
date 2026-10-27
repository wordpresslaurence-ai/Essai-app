<?php

namespace App\Services\Prospection;

use App\Models\Contact;

/**
 * Transforme les 40 champs bruts d'un candidat en colonnes de contact
 * normalisées (mêmes règles que l'import CSV : listes fermées par clé ou
 * libellé, booléen « ne plus contacter », date). Sépare la catégorie (par nom)
 * et les étiquettes.
 */
class MappeurContact
{
    /** Colonnes directes du contact présentes dans les 40 champs. */
    private const COLONNES = [
        'nom', 'prenom', 'fonction', 'email', 'telephone',
        'adresse_rue', 'adresse_code_postal', 'adresse_ville', 'region', 'adresse_pays',
        'numero_entreprise', 'numero_tva', 'site_web', 'linkedin', 'instagram', 'facebook',
        'langues', 'secteur', 'url_profil', 'temperature', 'origine', 'origine_precision',
        'url_sources', 'date_verification', 'discipline', 'discipline_precision',
        'experience_yoga', 'public_precision', 'formats_proposes', 'fait_observe',
        'angle_contact', 'role_commercial', 'priorite_prospection', 'interet_exprime',
        'besoin_exprime', 'notes',
    ];

    /**
     * @param  array<string, string>  $donnees
     * @return array{colonnes: array<string, mixed>, categorieNom: ?string, etiquettes: list<string>}
     */
    public function mapper(array $donnees): array
    {
        $colonnes = [];
        foreach (self::COLONNES as $champ) {
            if (filled($donnees[$champ] ?? null)) {
                $colonnes[$champ] = trim((string) $donnees[$champ]);
            }
        }

        // Type personne/entreprise.
        $colonnes['type'] = $this->estEntreprise($donnees['type'] ?? '')
            ? Contact::TYPE_ENTREPRISE
            : Contact::TYPE_PERSONNE;

        // Listes fermées : clé ou libellé accepté, valeur inconnue ignorée.
        foreach ([
            'temperature' => $this->libellesTemperatures(),
            'origine' => Contact::ORIGINES,
            'experience_yoga' => Contact::EXPERIENCES_YOGA,
            'priorite_prospection' => Contact::PRIORITES,
        ] as $champ => $map) {
            if (isset($colonnes[$champ])) {
                $cle = $this->cleCorrespondante($colonnes[$champ], $map);
                if ($cle === null) {
                    unset($colonnes[$champ]);
                } else {
                    $colonnes[$champ] = $cle;
                }
            }
        }

        // Booléen « ne plus contacter ».
        if (filled($donnees['ne_plus_contacter'] ?? null)) {
            $colonnes['ne_plus_contacter'] = $this->versBooleen($donnees['ne_plus_contacter']);
        }

        // Date de vérification (ou ignorée si illisible).
        if (isset($colonnes['date_verification'])) {
            $date = $this->versDate($colonnes['date_verification']);
            if ($date === null) {
                unset($colonnes['date_verification']);
            } else {
                $colonnes['date_verification'] = $date;
            }
        }

        // Étiquettes.
        $etiquettes = [];
        if (filled($donnees['etiquettes'] ?? null)) {
            foreach (preg_split('/[;,|]/', (string) $donnees['etiquettes']) as $nom) {
                $nom = trim($nom);
                if ($nom !== '') {
                    $etiquettes[] = $nom;
                }
            }
        }

        $categorieNom = filled($donnees['categorie'] ?? null) ? trim((string) $donnees['categorie']) : null;

        return ['colonnes' => $colonnes, 'categorieNom' => $categorieNom, 'etiquettes' => array_values(array_unique($etiquettes))];
    }

    private function estEntreprise(string $type): bool
    {
        $t = mb_strtolower(trim($type));

        return str_contains($t, 'entrep') || str_contains($t, 'company')
            || str_contains($t, 'société') || str_contains($t, 'societe')
            || $t === 'true' || $t === '1' || $t === 'vrai' || $t === 'oui';
    }

    private function versBooleen(string $valeur): bool
    {
        return in_array(mb_strtolower(trim($valeur)), ['1', 'oui', 'yes', 'true', 'vrai', 'x', 'y', 'o'], true);
    }

    private function versDate(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            $date = \DateTime::createFromFormat($format, $valeur);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** @param  array<string, string>  $map */
    private function cleCorrespondante(string $valeur, array $map): ?string
    {
        $v = mb_strtolower(trim($valeur));
        if ($v === '') {
            return null;
        }
        foreach ($map as $cle => $libelle) {
            if ($v === mb_strtolower((string) $cle) || $v === mb_strtolower((string) $libelle)) {
                return (string) $cle;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function libellesTemperatures(): array
    {
        return array_map(fn ($t) => $t[0], Contact::TEMPERATURES);
    }
}
