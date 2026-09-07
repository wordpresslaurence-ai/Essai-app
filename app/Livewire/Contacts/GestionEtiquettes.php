<?php

namespace App\Livewire\Contacts;

use App\Models\Etiquette;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionEtiquettes extends Component
{
    public string $nom = '';

    public ?string $couleur = '#6b7280';

    public function ajouter(): void
    {
        $donnees = $this->validate([
            'nom' => ['required', 'string', 'max:255', 'unique:etiquettes,nom'],
            'couleur' => ['nullable', 'string', 'max:20'],
        ]);

        Etiquette::create($donnees);

        $this->reset('nom');
        session()->flash('message', 'Étiquette ajoutée.');
    }

    public function supprimer(Etiquette $etiquette): void
    {
        $etiquette->delete();
        session()->flash('message', 'Étiquette supprimée.');
    }

    public function render(): View
    {
        return view('livewire.contacts.gestion-etiquettes', [
            'etiquettes' => Etiquette::withCount('contacts')->orderBy('nom')->get(),
        ]);
    }
}
