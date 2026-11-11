<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\Invitation;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tableau du parcours formation (brique 014) : de l'invitation à la séance
 * découverte jusqu'à l'inscription à la formation, avec les places par stage.
 * Repose sur le « rôle dans le parcours » choisi sur chaque session.
 */
#[Layout('layouts.app')]
class Parcours extends Component
{
    public function render(): View
    {
        $decouvertes = Seance::where('role_parcours', Seance::ROLE_DECOUVERTE)
            ->with(['inscriptions', 'invitations'])
            ->orderBy('date_debut')->get();

        $formations = Seance::where('role_parcours', Seance::ROLE_FORMATION)
            ->with(['inscriptions', 'lieuContact'])
            ->orderBy('date_debut')->get();

        $actifs = [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE];
        // Toujours des collections simples d'identifiants (une collection Eloquent vide
        // ne sait ni fusionner ni croiser des entiers).
        $entiers = fn ($collection) => collect($collection->all())->map(fn ($id) => (int) $id)->unique()->values();

        // --- Séances découverte : qui a été touché, inscrit, présent -----------
        $invites = $entiers($decouvertes->flatMap(fn (Seance $s) => $s->invitations
            ->where('statut', '!=', Invitation::STATUT_A_INVITER)->pluck('contact_id')));
        $inscrits = $entiers($decouvertes->flatMap(fn (Seance $s) => $s->inscriptions
            ->whereIn('statut', $actifs)->pluck('contact_id')));
        $presents = $entiers($decouvertes->flatMap(fn (Seance $s) => $s->inscriptions
            ->where('presence', Inscription::PRESENT)->pluck('contact_id')));

        $cohorte = $invites->merge($inscrits)->unique()->values();

        // --- Appels : un rendez-vous noté pour une personne de cette cohorte ---
        $appels = $cohorte->isEmpty() ? collect() : $entiers(
            Activite::where('type', Activite::TYPE_RDV)->whereIn('contact_id', $cohorte)->pluck('contact_id')
        );

        // --- Formation : inscrits confirmés à un stage -------------------------
        $formes = $entiers($formations->flatMap(fn (Seance $s) => $s->inscriptions
            ->where('statut', Inscription::STATUT_CONFIRME)->pluck('contact_id')));

        $etapes = [
            ['libelle' => 'Invités à une séance découverte', 'n' => $invites->count()],
            ['libelle' => 'Inscrits à une séance découverte', 'n' => $inscrits->count()],
            ['libelle' => 'Présents', 'n' => $presents->count()],
            ['libelle' => 'Ont réservé un appel', 'n' => $appels->count()],
            ['libelle' => 'Inscrits à la formation', 'n' => $formes->count()],
        ];
        $max = max(1, ...array_column($etapes, 'n'));
        foreach ($etapes as $i => $etape) {
            $etapes[$i]['largeur'] = (int) round($etape['n'] / $max * 100);
        }

        return view('livewire.sessions.parcours', [
            'vide' => $decouvertes->isEmpty() && $formations->isEmpty(),
            'etapes' => $etapes,
            'formesViaDecouverte' => $formes->intersect($cohorte)->count(),
            'decouvertes' => $decouvertes->map(fn (Seance $s) => [
                'seance' => $s,
                'invites' => $s->invitations->where('statut', '!=', Invitation::STATUT_A_INVITER)->count(),
                'inscrits' => $s->inscriptions->whereIn('statut', $actifs)->count(),
                'presents' => $s->inscriptions->where('presence', Inscription::PRESENT)->count(),
                'absents' => $s->inscriptions->where('presence', Inscription::ABSENT)->count(),
            ]),
            'formations' => $formations->map(fn (Seance $s) => [
                'seance' => $s,
                'confirmes' => $s->inscriptions->where('statut', Inscription::STATUT_CONFIRME)->count(),
                'attente' => $s->inscriptions->where('statut', Inscription::STATUT_ATTENTE)->count(),
            ]),
        ]);
    }
}
