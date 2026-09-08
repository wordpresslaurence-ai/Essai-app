<?php

namespace App\Livewire\Pipeline;

use App\Models\Contact;
use App\Models\Opportunite;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulaire extends Component
{
    public ?Opportunite $opportunite = null;

    public string $titre = '';

    public ?string $contact_id = null;

    public ?string $montant = null;

    public string $etape = Opportunite::ETAPE_NOUVEAU;

    public ?string $notes = null;

    public function mount(?Opportunite $opportunite = null): void
    {
        if ($opportunite && $opportunite->exists) {
            $this->opportunite = $opportunite;
            $this->titre = $opportunite->titre;
            $this->contact_id = $opportunite->contact_id ? (string) $opportunite->contact_id : null;
            $this->montant = $opportunite->montant !== null ? (string) $opportunite->montant : null;
            $this->etape = $opportunite->etape;
            $this->notes = $opportunite->notes;
        }
    }

    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'montant' => ['nullable', 'numeric', 'min:0'],
            'etape' => ['required', Rule::in(array_keys(Opportunite::libellesEtapes()))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function enregistrer()
    {
        $donnees = $this->validate();

        if ($this->opportunite) {
            // changerEtape gère la date de clôture si l'étape change.
            if ($this->opportunite->etape !== $donnees['etape']) {
                $this->opportunite->changerEtape($donnees['etape']);
            }
            $this->opportunite->update($donnees);
            $message = 'Opportunité modifiée.';
        } else {
            $op = Opportunite::make($donnees);
            $op->save();
            if (in_array($op->etape, [Opportunite::ETAPE_GAGNE, Opportunite::ETAPE_PERDU], true)) {
                $op->changerEtape($op->etape);
            }
            $message = 'Opportunité créée.';
        }

        session()->flash('message', $message);

        return $this->redirect(route('pipeline.index'), navigate: true);
    }

    public function supprimer()
    {
        $this->opportunite?->delete();
        session()->flash('message', 'Opportunité supprimée.');

        return $this->redirect(route('pipeline.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pipeline.formulaire', [
            'contacts' => Contact::actifs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'type']),
            'libelles' => Opportunite::libellesEtapes(),
        ]);
    }
}
