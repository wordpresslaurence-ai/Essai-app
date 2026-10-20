<?php

use App\Livewire\Contacts\Formulaire as ContactFormulaire;
use App\Livewire\Pipeline\Formulaire as PipelineFormulaire;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\Pole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->prana = Pole::create(['nom' => 'Prana']);
    $this->formation = Categorie::create(['pole_id' => $this->prana->id, 'nom' => 'Formation profs']);
});

it('enregistre la catégorie choisie sur un contact', function () {
    Livewire::test(ContactFormulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Durand')
        ->set('categorie_id', (string) $this->formation->id)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $contact = Contact::where('nom', 'Durand')->first();
    expect($contact->categorie_id)->toBe($this->formation->id);
    expect($contact->categorie->pole->nom)->toBe('Prana');
});

it('pré-remplit la catégorie du contact en mode édition', function () {
    $contact = Contact::factory()->create(['categorie_id' => $this->formation->id]);

    Livewire::test(ContactFormulaire::class, ['contact' => $contact])
        ->assertSet('categorie_id', (string) $this->formation->id);
});

it('refuse une catégorie inexistante sur un contact', function () {
    Livewire::test(ContactFormulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Durand')
        ->set('categorie_id', '999999')
        ->call('enregistrer')
        ->assertHasErrors(['categorie_id']);
});

it('enregistre la catégorie choisie sur une opportunité', function () {
    Livewire::test(PipelineFormulaire::class)
        ->set('titre', 'Stage de mars')
        ->set('categorie_id', (string) $this->formation->id)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $opp = Opportunite::where('titre', 'Stage de mars')->first();
    expect($opp->categorie_id)->toBe($this->formation->id);
    expect($opp->categorie->libelleComplet())->toBe('Prana · Formation profs');
});

it('pré-remplit la catégorie de l’opportunité avec celle du contact choisi (RF-5)', function () {
    $contact = Contact::factory()->create(['categorie_id' => $this->formation->id]);

    Livewire::test(PipelineFormulaire::class)
        ->set('contact_id', (string) $contact->id)
        ->assertSet('categorie_id', (string) $this->formation->id);
});

it('n’écrase pas une catégorie déjà choisie quand on sélectionne un contact', function () {
    $autre = Categorie::create(['pole_id' => $this->prana->id, 'nom' => 'Journée aura']);
    $contact = Contact::factory()->create(['categorie_id' => $autre->id]);

    Livewire::test(PipelineFormulaire::class)
        ->set('categorie_id', (string) $this->formation->id)
        ->set('contact_id', (string) $contact->id)
        ->assertSet('categorie_id', (string) $this->formation->id);
});
