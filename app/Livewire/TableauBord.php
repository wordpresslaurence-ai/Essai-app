<?php

namespace App\Livewire;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TableauBord extends Component
{
    /** Les tâches de suivi des séances passées récentes sont créées au passage. */
    public function mount(): void
    {
        app(\App\Services\Sessions\SuiviApresSeance::class)->creerTaches(auth()->user()?->name);
        app(\App\Services\Sessions\RappelsCycle::class)->creerTaches();
    }

    /** Coche une tâche du jour comme terminée. */
    public function terminerActivite(int $id): void
    {
        Activite::findOrFail($id)->basculer();
    }

    public function render(): View
    {
        // Aperçu du pipeline : compte + total par étape active.
        $parEtape = Opportunite::enCours()
            ->selectRaw('etape, count(*) as n, coalesce(sum(montant),0) as total')
            ->groupBy('etape')
            ->get()
            ->keyBy('etape');

        // Aperçu « en cours » : étapes non terminales uniquement (hors Gagné).
        $etapesEnCours = [Opportunite::ETAPE_NOUVEAU, Opportunite::ETAPE_QUALIFIE, Opportunite::ETAPE_PROPOSITION];

        $apercuPipeline = [];
        foreach ($etapesEnCours as $etape) {
            $ligne = $parEtape->get($etape);
            $apercuPipeline[$etape] = [
                'libelle' => Opportunite::libellesEtapes()[$etape],
                'couleur' => Opportunite::couleurEtape($etape),
                'n' => $ligne->n ?? 0,
                'total' => $ligne->total ?? 0,
            ];
        }

        return view('livewire.tableau-bord', [
            'nbContacts' => Contact::actifs()->count(),
            'nbEntreprises' => Contact::typeEntreprise()->actifs()->count(),
            'nbOpportunites' => Opportunite::enCours()->count(),
            'nbGagnees' => Opportunite::gagnees()->count(),
            'montantEnCours' => Opportunite::enCours()->sum('montant'),
            'derniers' => Contact::actifs()
                ->with('entreprise')
                ->latest()
                ->take(3)
                ->get(),
            'apercuPipeline' => $apercuPipeline,
            'contactsALaUne' => Contact::aLaUne()->actifs()->with('entreprise')->latest('updated_at')->take(6)->get(),
            'tachesDuJour' => Activite::duJourOuEnRetard()
                ->with('contact')
                ->orderBy('echeance')
                ->take(3)
                ->get(),
            'prochainesSessions' => Seance::aVenir()
                ->whereNotNull('date_debut')
                ->with('lieuContact')
                ->orderBy('date_debut')
                ->take(6)
                ->get(),
        ]);
    }
}
