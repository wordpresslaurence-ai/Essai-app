<?php

use App\Livewire\Sessions\Formulaire;
use App\Models\Categorie;
use App\Models\Pole;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('crée une session', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation profs']);

    Livewire::test(Formulaire::class)
        ->set('titre', 'Promo mars 2027')
        ->set('categorie_id', (string) $cat->id)
        ->set('capacite', '10')
        ->set('lieu', 'Namur')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $seance = Seance::where('titre', 'Promo mars 2027')->first();
    expect($seance)->not->toBeNull();
    expect($seance->capacite)->toBe(10);
    expect($seance->categorie_id)->toBe($cat->id);
});

it('exige un titre', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', '')
        ->call('enregistrer')
        ->assertHasErrors(['titre' => 'required']);
});

it('pré-remplit et modifie une session', function () {
    $seance = Seance::create(['titre' => 'Ancien', 'capacite' => 5]);

    Livewire::test(Formulaire::class, ['seance' => $seance])
        ->assertSet('titre', 'Ancien')
        ->assertSet('capacite', '5')
        ->set('titre', 'Nouveau')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect($seance->fresh()->titre)->toBe('Nouveau');
});

it('supprime une session', function () {
    $seance = Seance::create(['titre' => 'À supprimer']);

    Livewire::test(Formulaire::class, ['seance' => $seance])
        ->call('supprimer');

    expect(Seance::find($seance->id))->toBeNull();
});
