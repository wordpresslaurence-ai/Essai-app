<?php

namespace App\Livewire\Contacts;

use App\Models\Etiquette;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionEtiquettes extends Component
{
    public string $nom = '';

    public ?string $couleur = '#6b7280';

    // Édition en ligne
    public ?int $editId = null;

    public string $editNom = '';

    public ?string $editCouleur = null;

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

    public function editer(Etiquette $etiquette): void
    {
        $this->resetValidation();
        $this->editId = $etiquette->id;
        $this->editNom = $etiquette->nom;
        $this->editCouleur = $etiquette->couleur ?: '#6b7280';
    }

    public function enregistrerEdition(): void
    {
        $etiquette = Etiquette::findOrFail($this->editId);

        $donnees = $this->validate([
            'editNom' => ['required', 'string', 'max:255', Rule::unique('etiquettes', 'nom')->ignore($etiquette->id)],
            'editCouleur' => ['nullable', 'string', 'max:20'],
        ], [
            'editNom.unique' => 'Une étiquette porte déjà ce nom.',
        ]);

        $etiquette->update(['nom' => $donnees['editNom'], 'couleur' => $donnees['editCouleur']]);

        $this->editId = null;
        session()->flash('message', 'Étiquette modifiée.');
    }

    public function annulerEdition(): void
    {
        $this->editId = null;
        $this->resetValidation();
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
