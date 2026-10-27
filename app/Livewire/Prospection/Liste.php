<?php

namespace App\Livewire\Prospection;

use App\Models\RechercheProspection;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Liste extends Component
{
    public function supprimer(int $id): void
    {
        RechercheProspection::whereKey($id)->delete();
        session()->flash('message', 'Recherche supprimée.');
    }

    public function render(): View
    {
        return view('livewire.prospection.liste', [
            'recherches' => RechercheProspection::with('categorie')
                ->latest()
                ->get(),
        ]);
    }
}
