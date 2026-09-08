<?php

namespace App\Livewire\Pipeline;

use App\Models\Opportunite;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Tableau extends Component
{
    /** Afficher aussi la colonne des opportunités perdues. */
    #[Url]
    public bool $voirPerdus = false;

    /** Déplace une opportunité vers une autre étape. */
    public function deplacer(int $opportuniteId, string $etape): void
    {
        if (! array_key_exists($etape, Opportunite::libellesEtapes())) {
            return;
        }

        Opportunite::findOrFail($opportuniteId)->changerEtape($etape);
    }

    public function supprimer(int $opportuniteId): void
    {
        Opportunite::findOrFail($opportuniteId)->delete();
        session()->flash('message', 'Opportunité supprimée.');
    }

    public function render(): View
    {
        $etapes = Opportunite::etapesActives();
        if ($this->voirPerdus) {
            $etapes[] = Opportunite::ETAPE_PERDU;
        }

        $opportunites = Opportunite::with('contact')
            ->whereIn('etape', $etapes)
            ->orderByDesc('montant')
            ->get()
            ->groupBy('etape');

        $colonnes = [];
        foreach ($etapes as $etape) {
            $lot = $opportunites->get($etape, collect());
            $colonnes[$etape] = [
                'libelle' => Opportunite::libellesEtapes()[$etape],
                'couleur' => Opportunite::couleurEtape($etape),
                'items' => $lot,
                'total' => $lot->sum('montant'),
            ];
        }

        return view('livewire.pipeline.tableau', [
            'colonnes' => $colonnes,
            'libelles' => Opportunite::libellesEtapes(),
            'nbPerdus' => Opportunite::perdues()->count(),
        ]);
    }
}
