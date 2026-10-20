<?php

namespace App\Livewire\Sessions;

use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Fiche extends Component
{
    public Seance $seance;

    public ?string $contactAInscrire = null;

    public ?string $inscriptionErreur = null;

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;
    }

    public function inscrire(): void
    {
        $this->reset('inscriptionErreur');

        $donnees = $this->validate([
            'contactAInscrire' => ['required', 'exists:contacts,id'],
        ], [
            'contactAInscrire.required' => 'Choisis un contact à inscrire.',
        ]);

        $contact = Contact::find($donnees['contactAInscrire']);
        $this->seance->inscrire($contact);

        $this->reset('contactAInscrire');
        session()->flash('message', 'Inscription enregistrée.');
    }

    /** Promotion manuelle d'une personne en attente (si une place est libre). */
    public function promouvoir(Inscription $inscription): void
    {
        if ($inscription->seance_id !== $this->seance->id) {
            return;
        }

        if ($this->seance->estComplete()) {
            session()->flash('message', 'La session est complète : libère une place d’abord.');

            return;
        }

        $inscription->update(['statut' => Inscription::STATUT_CONFIRME]);
        session()->flash('message', 'Personne promue depuis la liste d’attente.');
    }

    public function desinscrire(Inscription $inscription): void
    {
        if ($inscription->seance_id !== $this->seance->id) {
            return;
        }

        $etaitConfirme = $inscription->estConfirme();
        $inscription->delete();

        if ($etaitConfirme) {
            $this->seance->promouvoirSuivant(); // promotion auto
        }

        session()->flash('message', 'Inscription retirée.');
    }

    public function render(): View
    {
        $this->seance->load(['categorie.pole', 'inscriptions.contact']);

        $actifs = $this->seance->inscriptions
            ->whereIn('statut', [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE])
            ->pluck('contact_id')->all();

        $contactsDispo = Contact::actifs()
            ->whereNotIn('id', $actifs)
            ->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'type']);

        return view('livewire.sessions.fiche', [
            'confirmes' => $this->seance->confirmes(),
            'enAttente' => $this->seance->enAttente(),
            'contactsDispo' => $contactsDispo,
        ]);
    }
}
