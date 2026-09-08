<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Models\Opportunite;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TableauBord extends Component
{
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
                ->take(5)
                ->get(),
            'apercuPipeline' => $apercuPipeline,
        ]);
    }
}
