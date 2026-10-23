<?php

namespace Database\Seeders;

use App\Models\Etiquette;
use Illuminate\Database\Seeder;

/**
 * Liste d'étiquettes de départ, adaptée à l'activité (Prana Vidya + Digital).
 * Idempotent : relançable sans créer de doublons (firstOrCreate sur le nom).
 * À lancer une seule fois :  php artisan db:seed --class=EtiquettesDepartSeeder
 *
 * Libre à toi d'en supprimer ou d'en ajouter ensuite dans Réglages → Étiquettes.
 */
class EtiquettesDepartSeeder extends Seeder
{
    public function run(): void
    {
        $etiquettes = [
            // Statut de la relation
            ['Client', '#16A34A'],
            ['Prospect', '#2563EB'],
            ['Partenaire', '#6C74B4'],
            ['À nourrir', '#D97706'],
            // Prana / yoga
            ['Séance découverte faite', '#4E8577'],
            ['Élève actuel', '#0EA5A4'],
            ['Intéressé formation profs', '#7C3AED'],
            // Digital
            ['Client site web', '#1877F2'],
        ];

        foreach ($etiquettes as [$nom, $couleur]) {
            Etiquette::firstOrCreate(['nom' => $nom], ['couleur' => $couleur]);
        }
    }
}
