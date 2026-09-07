<?php

namespace App\Livewire;

use App\Models\Contact;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TableauBord extends Component
{
    public function render(): View
    {
        return view('livewire.tableau-bord', [
            'nbContacts' => Contact::actifs()->count(),
            'nbEntreprises' => Contact::typeEntreprise()->actifs()->count(),
            'nbPersonnes' => Contact::typePersonne()->actifs()->count(),
            'nbArchives' => Contact::archives()->count(),
            'derniers' => Contact::actifs()
                ->with('entreprise')
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
