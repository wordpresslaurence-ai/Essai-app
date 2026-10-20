<?php

namespace App\Livewire\Activites;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Pole;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Liste extends Component
{
    /** Filtre : a_faire (défaut), terminees, toutes. */
    #[Url]
    public string $filtre = 'a_faire';

    /** Filtre par pôle (id) — via le contact lié. */
    #[Url]
    public string $pole = '';

    /** Filtre par catégorie (id) — via le contact lié. */
    #[Url]
    public string $categorie = '';

    /** Changer de pôle réinitialise la catégorie choisie. */
    public function updatedPole(): void
    {
        $this->categorie = '';
    }

    // Ajout rapide
    public string $titre = '';

    public string $type = Activite::TYPE_TACHE;

    public ?string $echeance = null;

    public ?string $contact_id = null;

    public function ajouter(): void
    {
        $donnees = $this->validate([
            'titre' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Activite::libellesTypes()))],
            'echeance' => ['nullable', 'date'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
        ]);

        Activite::create($donnees);

        $this->reset('titre', 'echeance', 'contact_id');
        session()->flash('message', 'Activité ajoutée.');
    }

    public function basculer(int $id): void
    {
        Activite::findOrFail($id)->basculer();
    }

    public function supprimer(int $id): void
    {
        Activite::findOrFail($id)->delete();
        session()->flash('message', 'Activité supprimée.');
    }

    public function render(): View
    {
        $activites = Activite::with(['contact', 'opportunite'])
            ->when($this->filtre === 'a_faire', fn ($q) => $q->aFaire())
            ->when($this->filtre === 'terminees', fn ($q) => $q->terminees())
            ->when($this->categorie !== '', fn ($q) => $q->whereHas(
                'contact',
                fn ($c) => $c->where('categorie_id', $this->categorie)
            ))
            ->when($this->categorie === '' && $this->pole !== '', fn ($q) => $q->whereHas(
                'contact',
                fn ($c) => $c->whereHas('categorie', fn ($cat) => $cat->where('pole_id', $this->pole))
            ))
            ->orderByRaw('echeance is null')
            ->orderBy('echeance')
            ->get();

        return view('livewire.activites.liste', [
            'activites' => $activites,
            'libelles' => Activite::libellesTypes(),
            'contacts' => Contact::actifs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'type']),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}
