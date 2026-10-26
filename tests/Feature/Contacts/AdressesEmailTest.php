<?php

use App\Livewire\Contacts\Formulaire;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('enregistre des adresses e-mail secondaires, sans vides ni doublon de la principale', function () {
    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Durand')
        ->set('email', 'marie@exemple.be')
        ->set('adressesSecondaires', ['marie.pro@exemple.be', '', 'marie@exemple.be', 'marie.perso@exemple.be'])
        ->call('enregistrer')
        ->assertHasNoErrors();

    $contact = Contact::where('nom', 'Durand')->firstOrFail();

    // La principale et la ligne vide sont exclues ; il reste les deux secondaires.
    expect($contact->adressesEmail->pluck('adresse')->sort()->values()->all())
        ->toBe(['marie.perso@exemple.be', 'marie.pro@exemple.be']);
});

it('met à jour les adresses secondaires à l’édition', function () {
    $contact = Contact::factory()->create(['nom' => 'Durand', 'email' => 'marie@exemple.be']);
    $contact->adressesEmail()->create(['adresse' => 'ancienne@exemple.be']);

    Livewire::test(Formulaire::class, ['contact' => $contact])
        ->assertSet('adressesSecondaires', ['ancienne@exemple.be'])
        ->set('adressesSecondaires', ['nouvelle@exemple.be'])
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect($contact->refresh()->adressesEmail->pluck('adresse')->all())
        ->toBe(['nouvelle@exemple.be']);
});

it('retire une ligne d’adresse secondaire', function () {
    Livewire::test(Formulaire::class)
        ->set('adressesSecondaires', ['a@x.be', 'b@x.be', 'c@x.be'])
        ->call('retirerAdresse', 1)
        ->assertSet('adressesSecondaires', ['a@x.be', 'c@x.be']);
});

it('refuse une adresse secondaire invalide', function () {
    Livewire::test(Formulaire::class)
        ->set('nom', 'Durand')
        ->set('adressesSecondaires', ['pas-un-email'])
        ->call('enregistrer')
        ->assertHasErrors(['adressesSecondaires.0']);
});

it('signale un doublon quand l’e-mail saisi est l’adresse secondaire d’un autre contact', function () {
    $existant = Contact::factory()->create(['nom' => 'DejaLa', 'email' => 'principal@exemple.be']);
    $existant->adressesEmail()->create(['adresse' => 'secondaire@exemple.be']);

    Livewire::test(Formulaire::class)
        ->set('nom', 'Nouveau')
        ->set('email', 'SECONDAIRE@exemple.be') // insensible à la casse
        ->assertSee('DejaLa');
});
