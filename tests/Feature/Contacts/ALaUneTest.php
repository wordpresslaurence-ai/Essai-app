<?php

use App\Livewire\Contacts\FicheDetail;
use App\Livewire\TableauBord;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('bascule un contact « à la une » depuis sa fiche (étoile)', function () {
    $contact = Contact::factory()->create(['a_la_une' => false]);

    $c = Livewire::test(FicheDetail::class, ['contact' => $contact]);

    $c->call('basculerALaUne');
    expect($contact->fresh()->a_la_une)->toBeTrue();

    $c->call('basculerALaUne');
    expect($contact->fresh()->a_la_une)->toBeFalse();
});

it('affiche les contacts à la une sur le tableau de bord', function () {
    Contact::factory()->create(['nom' => 'Vedette', 'a_la_une' => true]);
    Contact::factory()->create(['nom' => 'Ordinaire', 'a_la_une' => false]);

    Livewire::test(TableauBord::class)
        ->assertSee('Contacts à la une')
        ->assertSee('Vedette');
});
