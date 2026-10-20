<?php

use App\Livewire\Contacts\FicheDetail;
use App\Models\Contact;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('affiche les inscriptions du contact sur sa fiche', function () {
    $contact = Contact::factory()->create();
    $seance = Seance::create(['titre' => 'Promo mars 2027', 'capacite' => 10, 'date_debut' => now()->addMonth()]);
    $seance->inscrire($contact);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('Inscriptions')
        ->assertSee('Promo mars 2027')
        ->assertSee('Confirmé');
});
