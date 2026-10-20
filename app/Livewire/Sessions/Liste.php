<?php

namespace App\Livewire\Sessions;

use App\Models\Pole;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Liste des sessions (séances) : places prises/restantes, liste d'attente,
 * filtres Pôle → Catégorie, onglets À venir / Passées.
 */
#[Layout('layouts.app')]
class Liste extends Component
{
    /** Onglet : 'avenir' (défaut) ou 'passees'. */
    #[Url]
    public string $onglet = 'avenir';

    /** Filtre par pôle (id). */
    #[Url]
    public string $pole = '';

    /** Filtre par catégorie (id). */
    #[Url]
    public string $categorie = '';

    public function updatedPole(): void
    {
        $this->categorie = '';
    }

    public function render(): View
    {
        $seances = Seance::with('categorie.pole')
            ->withCount(['inscriptions as confirmes_count' => fn ($q) => $q->where('statut', 'confirme')])
            ->withCount(['inscriptions as attente_count' => fn ($q) => $q->where('statut', 'attente')])
            ->when($this->onglet === 'passees', fn ($q) => $q->passees(), fn ($q) => $q->aVenir())
            ->when($this->categorie !== '', fn ($q) => $q->where('categorie_id', $this->categorie))
            ->when(
                $this->categorie === '' && $this->pole !== '',
                fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $this->pole))
            )
            ->orderByRaw('date_debut is null')
            ->orderBy('date_debut', $this->onglet === 'passees' ? 'desc' : 'asc')
            ->get();

        return view('livewire.sessions.liste', [
            'seances' => $seances,
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}
