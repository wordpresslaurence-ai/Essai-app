<?php

namespace App\Livewire\Organisation;

use App\Models\Categorie;
use App\Models\Pole;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gestion des Pôles et Catégories : l'utilisatrice crée/supprime ses propres
 * « sections » (notamment pour une nouvelle offre ou un nouveau produit).
 */
#[Layout('layouts.app')]
class Gestion extends Component
{
    // Nouveau pôle
    public string $poleNom = '';

    public ?string $poleCouleur = '#4E8577';

    // Nouvelle catégorie
    public ?string $categoriePoleId = null;

    public string $categorieNom = '';

    public ?string $categorieCouleur = '#6C74B4';

    // Renommage en ligne
    public ?int $poleEditId = null;

    public string $poleEditNom = '';

    public ?int $categorieEditId = null;

    public string $categorieEditNom = '';

    public function ajouterPole(): void
    {
        $donnees = $this->validate([
            'poleNom' => ['required', 'string', 'max:255', 'unique:poles,nom'],
            'poleCouleur' => ['nullable', 'string', 'max:20'],
        ]);

        Pole::create(['nom' => $donnees['poleNom'], 'couleur' => $donnees['poleCouleur']]);

        $this->reset('poleNom');
        session()->flash('message', 'Pôle ajouté.');
    }

    public function supprimerPole(Pole $pole): void
    {
        $pole->delete(); // catégories supprimées, contacts/opportunités détachés
        session()->flash('message', 'Pôle supprimé.');
    }

    public function editerPole(Pole $pole): void
    {
        $this->resetValidation();
        $this->poleEditId = $pole->id;
        $this->poleEditNom = $pole->nom;
        $this->categorieEditId = null;
    }

    public function renommerPole(): void
    {
        $pole = Pole::findOrFail($this->poleEditId);

        $donnees = $this->validate([
            'poleEditNom' => ['required', 'string', 'max:255', Rule::unique('poles', 'nom')->ignore($pole->id)],
        ]);

        $pole->update(['nom' => $donnees['poleEditNom']]);
        $this->poleEditId = null;
        session()->flash('message', 'Pôle renommé.');
    }

    public function annulerEditionPole(): void
    {
        $this->poleEditId = null;
        $this->resetValidation();
    }

    public function ajouterCategorie(): void
    {
        $donnees = $this->validate([
            'categoriePoleId' => ['required', 'exists:poles,id'],
            'categorieNom' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'nom')->where('pole_id', $this->categoriePoleId),
            ],
            'categorieCouleur' => ['nullable', 'string', 'max:20'],
        ], [
            'categoriePoleId.required' => 'Choisis un pôle.',
            'categorieNom.unique' => 'Cette catégorie existe déjà dans ce pôle.',
        ]);

        Categorie::create([
            'pole_id' => $donnees['categoriePoleId'],
            'nom' => $donnees['categorieNom'],
            'couleur' => $donnees['categorieCouleur'],
        ]);

        $this->reset('categorieNom');
        session()->flash('message', 'Catégorie ajoutée.');
    }

    public function supprimerCategorie(Categorie $categorie): void
    {
        $categorie->delete(); // contacts/opportunités détachés
        session()->flash('message', 'Catégorie supprimée.');
    }

    public function editerCategorie(Categorie $categorie): void
    {
        $this->resetValidation();
        $this->categorieEditId = $categorie->id;
        $this->categorieEditNom = $categorie->nom;
        $this->poleEditId = null;
    }

    public function renommerCategorie(): void
    {
        $categorie = Categorie::findOrFail($this->categorieEditId);

        $donnees = $this->validate([
            'categorieEditNom' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'nom')->where('pole_id', $categorie->pole_id)->ignore($categorie->id),
            ],
        ], [
            'categorieEditNom.unique' => 'Cette catégorie existe déjà dans ce pôle.',
        ]);

        $categorie->update(['nom' => $donnees['categorieEditNom']]);
        $this->categorieEditId = null;
        session()->flash('message', 'Catégorie renommée.');
    }

    public function annulerEditionCategorie(): void
    {
        $this->categorieEditId = null;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.organisation.gestion', [
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}
