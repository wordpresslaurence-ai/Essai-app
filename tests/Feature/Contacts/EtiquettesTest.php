<?php

use App\Livewire\Contacts\GestionEtiquettes;
use App\Models\Etiquette;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('crée une étiquette', function () {
    Livewire::test(GestionEtiquettes::class)
        ->set('nom', 'Client')
        ->set('couleur', '#16a34a')
        ->call('ajouter')
        ->assertHasNoErrors();

    expect(Etiquette::where('nom', 'Client')->exists())->toBeTrue();
});

it('renomme et recolore une étiquette', function () {
    $et = Etiquette::create(['nom' => 'Prospet', 'couleur' => '#2563eb']);

    Livewire::test(GestionEtiquettes::class)
        ->call('editer', $et->id)
        ->assertSet('editNom', 'Prospet')
        ->set('editNom', 'Prospect')
        ->set('editCouleur', '#111111')
        ->call('enregistrerEdition')
        ->assertHasNoErrors();

    $et->refresh();
    expect($et->nom)->toBe('Prospect');
    expect($et->couleur)->toBe('#111111');
});

it('refuse de renommer avec un nom déjà pris', function () {
    Etiquette::create(['nom' => 'Client']);
    $autre = Etiquette::create(['nom' => 'Fournisseur']);

    Livewire::test(GestionEtiquettes::class)
        ->call('editer', $autre->id)
        ->set('editNom', 'Client')
        ->call('enregistrerEdition')
        ->assertHasErrors(['editNom']);

    expect($autre->fresh()->nom)->toBe('Fournisseur');
});

it('autorise à réenregistrer une étiquette avec son propre nom', function () {
    $et = Etiquette::create(['nom' => 'Client', 'couleur' => '#16a34a']);

    Livewire::test(GestionEtiquettes::class)
        ->call('editer', $et->id)
        ->set('editCouleur', '#000000')
        ->call('enregistrerEdition')
        ->assertHasNoErrors();

    expect($et->fresh()->couleur)->toBe('#000000');
});

it('supprime une étiquette', function () {
    $et = Etiquette::create(['nom' => 'À supprimer']);

    Livewire::test(GestionEtiquettes::class)
        ->call('supprimer', $et->id)
        ->assertHasNoErrors();

    expect(Etiquette::find($et->id))->toBeNull();
});

it('exige une connexion pour gérer les étiquettes', function () {
    auth()->logout();
    $this->get(route('etiquettes.index'))->assertRedirect(route('login'));
});
