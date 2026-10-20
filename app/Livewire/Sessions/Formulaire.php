<?php

namespace App\Livewire\Sessions;

use App\Models\Pole;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulaire extends Component
{
    public ?Seance $seance = null;

    public string $titre = '';

    public ?string $categorie_id = null;

    public ?string $date_debut = null;

    public ?string $lieu = null;

    public ?string $capacite = null;

    public ?string $notes = null;

    public function mount(?Seance $seance = null): void
    {
        if ($seance && $seance->exists) {
            $this->seance = $seance;
            $this->titre = $seance->titre;
            $this->categorie_id = $seance->categorie_id ? (string) $seance->categorie_id : null;
            $this->date_debut = $seance->date_debut?->format('Y-m-d\TH:i');
            $this->lieu = $seance->lieu;
            $this->capacite = $seance->capacite !== null ? (string) $seance->capacite : null;
            $this->notes = $seance->notes;
        }
    }

    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'categorie_id' => ['nullable', 'exists:categories,id'],
            'date_debut' => ['nullable', 'date'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'capacite' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function enregistrer()
    {
        $donnees = $this->validate();
        $donnees['capacite'] = $donnees['capacite'] !== null && $donnees['capacite'] !== ''
            ? (int) $donnees['capacite'] : null;

        if ($this->seance) {
            $this->seance->update($donnees);
            $cible = $this->seance;
            $message = 'Session modifiée.';
        } else {
            $cible = Seance::create($donnees);
            $message = 'Session créée.';
        }

        session()->flash('message', $message);

        return $this->redirect(route('sessions.fiche', $cible), navigate: true);
    }

    public function supprimer()
    {
        $this->seance?->delete();
        session()->flash('message', 'Session supprimée.');

        return $this->redirect(route('sessions.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.sessions.formulaire', [
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}
