<?php

namespace App\Livewire\Activites;

use App\Models\Activite;
use App\Models\Contact;
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
            ->orderByRaw('echeance is null')
            ->orderBy('echeance')
            ->get();

        return view('livewire.activites.liste', [
            'activites' => $activites,
            'libelles' => Activite::libellesTypes(),
            'contacts' => Contact::actifs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'type']),
        ]);
    }
}
