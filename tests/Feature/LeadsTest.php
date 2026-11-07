<?php

use App\Livewire\Contacts\Formulaire;
use App\Livewire\Contacts\Liste;
use App\Livewire\TableauBord;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('enregistre une origine et une température : le contact devient un lead', function () {
    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Martinez')
        ->set('prenom', 'Sophia')
        ->set('origine', 'linkedin')
        ->set('temperature', 'chaud')
        ->call('enregistrer');

    $contact = Contact::where('nom', 'Martinez')->first();
    expect($contact->estLead())->toBeTrue();
    expect($contact->origine)->toBe('linkedin');
    expect($contact->temperature)->toBe('chaud');
});

it('filtre la liste des contacts par origine', function () {
    Contact::factory()->create(['nom' => 'VientDInsta', 'origine' => 'instagram']);
    Contact::factory()->create(['nom' => 'VientDunSalon', 'origine' => 'salon_evenement']);

    Livewire::test(Liste::class)
        ->set('origine', 'instagram')
        ->assertSee('VientDInsta')
        ->assertDontSee('VientDunSalon');
});

it('filtre la liste par température', function () {
    Contact::factory()->create(['nom' => 'LeadChaud', 'temperature' => 'chaud']);
    Contact::factory()->create(['nom' => 'SansTemp', 'temperature' => null]);

    Livewire::test(Liste::class)
        ->set('temperature', 'chaud')
        ->assertSee('LeadChaud')
        ->assertDontSee('SansTemp');
});
