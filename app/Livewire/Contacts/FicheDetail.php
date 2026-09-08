<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FicheDetail extends Component
{
    public Contact $contact;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
    }

    public function archiver(): void
    {
        $this->contact->archiver();
        session()->flash('message', 'Contact archivé.');
        $this->contact->refresh();
    }

    public function desarchiver(): void
    {
        $this->contact->desarchiver();
        session()->flash('message', 'Contact désarchivé.');
        $this->contact->refresh();
    }

    public function supprimer()
    {
        // Les personnes rattachées sont détachées automatiquement
        // (contrainte nullOnDelete de la migration), jamais supprimées (EF-04).
        $this->contact->delete();
        session()->flash('message', 'Contact supprimé.');

        return $this->redirect(route('contacts.index'), navigate: true);
    }

    public function render(): View
    {
        $this->contact->load([
            'entreprise',
            'personnes',
            'etiquettes',
            'opportunites' => fn ($q) => $q->orderByDesc('created_at'),
            'activites' => fn ($q) => $q->with('opportunite')->orderByRaw('echeance is null')->orderBy('echeance'),
        ]);

        return view('livewire.contacts.fiche-detail');
    }
}
