<?php

namespace App\Services\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\ModeleMessage;
use App\Models\Seance;

/**
 * Suivi après une séance (brique 014) : dès le lendemain, une tâche par
 * participant avec le message prêt — remerciement pour les présents, invitation
 * à la prochaine date pour les absents. Sans IA, sans envoi automatique.
 *
 * Il n'y a pas de tâche planifiée sur l'hébergement : les tâches sont créées à
 * l'ouverture du tableau de bord ou de l'assistant (appel idempotent).
 */
class SuiviApresSeance
{
    /** On ne crée plus de suivi pour une séance plus ancienne que cela. */
    public const FENETRE_JOURS = 14;

    /**
     * Crée les tâches de suivi manquantes pour les séances passées récentes.
     * Retourne le nombre de tâches créées.
     */
    public function creerTaches(?string $moi = null): int
    {
        $inscriptions = Inscription::query()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->whereNull('suivi_cree_le')
            ->whereNull('suivi_envoye_le')
            // Pour un cycle, le suivi se fait après la DERNIÈRE date.
            ->whereHas('seance', fn ($q) => $q
                ->whereNotNull('date_debut')
                ->where(fn ($d) => $d
                    ->where(fn ($simple) => $simple->whereNull('date_fin')
                        ->where('date_debut', '<', now()->startOfDay())
                        ->where('date_debut', '>=', now()->subDays(self::FENETRE_JOURS)->startOfDay()))
                    ->orWhere(fn ($cycle) => $cycle->whereNotNull('date_fin')
                        ->where('date_fin', '<', now()->startOfDay())
                        ->where('date_fin', '>=', now()->subDays(self::FENETRE_JOURS)->startOfDay()))))
            ->with(['seance.lieuContact', 'contact.entreprise'])
            ->get();

        foreach ($inscriptions as $inscription) {
            $texte = $this->texte($inscription, $moi);
            $lendemain = $inscription->seance->fin()->copy()->addDay()->startOfDay();

            Activite::create([
                'titre' => self::titreTache($inscription),
                'type' => Activite::TYPE_TACHE,
                'contact_id' => $inscription->contact_id,
                'echeance' => $lendemain->isPast() ? now()->startOfDay() : $lendemain,
                'notes' => $this->notesTache($inscription, $texte),
            ]);

            $inscription->update(['suivi_cree_le' => now()]);
        }

        return $inscriptions->count();
    }

    /**
     * Message de suivi pour une inscription, selon la présence.
     *
     * @return array{objet: string, corps: string}
     */
    public function texte(Inscription $inscription, ?string $moi = null): array
    {
        $seance = $inscription->seance;

        return ModeleMessage::pourSuivi($inscription->estAbsent())
            ->rendre($inscription->contact, $seance, $moi, $this->prochaine($seance));
    }

    /**
     * Prochaine session du même type, proposée aux absents : même rôle dans le
     * parcours, sinon même catégorie, sinon même titre.
     */
    public function prochaine(Seance $seance): ?Seance
    {
        return Seance::query()
            ->whereKeyNot($seance->id)
            ->where('date_debut', '>', now())
            ->when(
                filled($seance->role_parcours),
                fn ($q) => $q->where('role_parcours', $seance->role_parcours),
                fn ($q) => $q->when(
                    $seance->categorie_id,
                    fn ($c) => $c->where('categorie_id', $seance->categorie_id),
                    fn ($c) => $c->where('titre', $seance->titre),
                ),
            )
            ->orderBy('date_debut')
            ->first();
    }

    /** Met à jour la tâche encore ouverte quand la présence change. */
    public function rafraichirTache(Inscription $inscription, ?string $moi = null): void
    {
        Activite::where('contact_id', $inscription->contact_id)
            ->whereNull('terminee_at')
            ->where('titre', self::titreTache($inscription))
            ->update(['notes' => $this->notesTache($inscription, $this->texte($inscription, $moi))]);
    }

    /** Clôture la tâche de suivi (le message vient d'être envoyé). */
    public function cloturerTache(Inscription $inscription): void
    {
        Activite::where('contact_id', $inscription->contact_id)
            ->whereNull('terminee_at')
            ->where('titre', self::titreTache($inscription))
            ->update(['terminee_at' => now()]);
    }

    public static function titreTache(Inscription $inscription): string
    {
        return 'Suivi après « '.$inscription->seance->titre.' » : '.$inscription->contact->nomComplet();
    }

    /** @param  array{objet: string, corps: string}  $texte */
    private function notesTache(Inscription $inscription, array $texte): string
    {
        $entete = match ($inscription->presence) {
            Inscription::ABSENT => 'Absent(e) à la séance : invitation à la prochaine date.',
            Inscription::PRESENT => 'Présent(e) à la séance.',
            default => 'Présence non notée : message « présent » par défaut. Note la présence depuis le suivi de la session pour l’ajuster.',
        };

        return $entete."\n\n".(filled($texte['objet']) ? 'Objet : '.$texte['objet']."\n\n" : '').$texte['corps'];
    }
}
