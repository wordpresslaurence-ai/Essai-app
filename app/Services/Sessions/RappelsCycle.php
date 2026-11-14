<?php

namespace App\Services\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\SeanceDate;

/**
 * Rappel de la veille (brique 017) : pour chaque date de session qui a lieu
 * d'ici demain soir, UNE tâche « Rappel aux inscrits » avec le lien vers le
 * message groupé prêt. Sans tâche planifiée sur l'hébergement : l'appel
 * (idempotent) est fait à l'ouverture du tableau de bord et de l'assistant.
 */
class RappelsCycle
{
    public function creerTaches(): int
    {
        $dates = SeanceDate::query()
            ->whereNull('rappel_cree_le')
            ->whereBetween('date_debut', [now(), now()->addDay()->endOfDay()])
            ->whereHas('seance.inscriptions', fn ($q) => $q->where('statut', Inscription::STATUT_CONFIRME))
            ->with('seance')
            ->get();

        foreach ($dates as $date) {
            Activite::create([
                'titre' => self::titreTache($date),
                'type' => Activite::TYPE_TACHE,
                'echeance' => now()->startOfDay(),
                'notes' => 'Le message de rappel, avec le lien de visio, est prêt pour chaque inscrit : '
                    .route('sessions.dates', ['seance' => $date->seance_id, 'date' => $date->id, 'mode' => 'rappel']),
            ]);

            $date->update(['rappel_cree_le' => now()]);
        }

        return $dates->count();
    }

    public static function titreTache(SeanceDate $date): string
    {
        return 'Rappel aux inscrits : « '.$date->seance->titre.' », '.$date->date_debut->translatedFormat('l j F');
    }
}
