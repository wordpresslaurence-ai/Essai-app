<?php

namespace App\Livewire\Activites;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Opportunite;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulaire extends Component
{
    public ?Activite $activite = null;

    public string $titre = '';

    public string $type = Activite::TYPE_TACHE;

    public ?string $contact_id = null;

    public ?string $opportunite_id = null;

    public ?string $echeance = null;

    public ?string $notes = null;

    public function mount(?Activite $activite = null): void
    {
        if ($activite && $activite->exists) {
            $this->activite = $activite;
            $this->titre = $activite->titre;
            $this->type = $activite->type;
            $this->contact_id = $activite->contact_id ? (string) $activite->contact_id : null;
            $this->opportunite_id = $activite->opportunite_id ? (string) $activite->opportunite_id : null;
            $this->echeance = $activite->echeance?->format('Y-m-d\TH:i');
            $this->notes = $activite->notes;

            return;
        }

        // Création depuis une fiche : pré-sélectionne le contact (?contact=ID)
        // et, éventuellement, l'opportunité (?opportunite=ID).
        $contactId = request()->integer('contact');
        if ($contactId && Contact::whereKey($contactId)->exists()) {
            $this->contact_id = (string) $contactId;
        }

        $opportuniteId = request()->integer('opportunite');
        if ($opportuniteId && Opportunite::whereKey($opportuniteId)->exists()) {
            $this->opportunite_id = (string) $opportuniteId;
        }
    }

    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Activite::libellesTypes()))],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'opportunite_id' => ['nullable', 'exists:opportunites,id'],
            'echeance' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function enregistrer()
    {
        $donnees = $this->validate();

        if ($this->activite) {
            $this->activite->update($donnees);
            $message = 'Activité modifiée.';
        } else {
            Activite::create($donnees);
            $message = 'Activité créée.';
        }

        session()->flash('message', $message);

        return $this->redirect(route('activites.index'), navigate: true);
    }

    public function terminer()
    {
        $this->activite?->update(['terminee_at' => now()]);
        session()->flash('message', 'Activité marquée terminée.');

        return $this->redirect(route('activites.index'), navigate: true);
    }

    public function rouvrir()
    {
        $this->activite?->update(['terminee_at' => null]);
        session()->flash('message', 'Activité rouverte.');

        return $this->redirect(route('activites.index'), navigate: true);
    }

    public function supprimer()
    {
        $this->activite?->delete();
        session()->flash('message', 'Activité supprimée.');

        return $this->redirect(route('activites.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.activites.formulaire', [
            'libelles' => Activite::libellesTypes(),
            'contacts' => Contact::actifs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'type']),
            'opportunites' => Opportunite::orderBy('titre')->get(['id', 'titre']),
        ]);
    }
}
