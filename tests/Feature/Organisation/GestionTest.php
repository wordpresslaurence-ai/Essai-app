<?php

use App\Livewire\Organisation\Gestion;
use App\Models\Categorie;
use App\Models\Pole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('affiche l’écran de gestion des pôles et catégories', function () {
    $prana = Pole::create(['nom' => 'Prana']);
    Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs']);

    Livewire::test(Gestion::class)
        ->assertOk()
        ->assertSee('Prana')
        ->assertSee('Formation profs');
});

it('crée un nouveau pôle', function () {
    Livewire::test(Gestion::class)
        ->set('poleNom', 'Digital')
        ->set('poleCouleur', '#6C74B4')
        ->call('ajouterPole')
        ->assertHasNoErrors()
        ->assertSee('Digital');

    expect(Pole::where('nom', 'Digital')->exists())->toBeTrue();
});

it('crée une nouvelle catégorie dans un pôle', function () {
    $prana = Pole::create(['nom' => 'Prana']);

    Livewire::test(Gestion::class)
        ->set('categoriePoleId', $prana->id)
        ->set('categorieNom', 'Journée aura')
        ->call('ajouterCategorie')
        ->assertHasNoErrors()
        ->assertSee('Journée aura');

    expect(Categorie::where('nom', 'Journée aura')->where('pole_id', $prana->id)->exists())->toBeTrue();
});

it('exige un nom de pôle', function () {
    Livewire::test(Gestion::class)
        ->set('poleNom', '')
        ->call('ajouterPole')
        ->assertHasErrors(['poleNom' => 'required']);
});

it('refuse deux pôles de même nom', function () {
    Pole::create(['nom' => 'Prana']);

    Livewire::test(Gestion::class)
        ->set('poleNom', 'Prana')
        ->call('ajouterPole')
        ->assertHasErrors(['poleNom']);
});

it('exige de choisir un pôle pour une catégorie', function () {
    Livewire::test(Gestion::class)
        ->set('categoriePoleId', null)
        ->set('categorieNom', 'Sites web')
        ->call('ajouterCategorie')
        ->assertHasErrors(['categoriePoleId']);
});

it('refuse deux catégories de même nom dans un pôle', function () {
    $prana = Pole::create(['nom' => 'Prana']);
    Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs']);

    Livewire::test(Gestion::class)
        ->set('categoriePoleId', $prana->id)
        ->set('categorieNom', 'Formation profs')
        ->call('ajouterCategorie')
        ->assertHasErrors(['categorieNom']);
});

it('autorise le même nom de catégorie dans deux pôles différents', function () {
    $prana = Pole::create(['nom' => 'Prana']);
    $digital = Pole::create(['nom' => 'Digital']);
    Categorie::create(['pole_id' => $prana->id, 'nom' => 'Prospection']);

    Livewire::test(Gestion::class)
        ->set('categoriePoleId', $digital->id)
        ->set('categorieNom', 'Prospection')
        ->call('ajouterCategorie')
        ->assertHasNoErrors();

    expect(Categorie::where('nom', 'Prospection')->count())->toBe(2);
});

it('supprime un pôle', function () {
    $pole = Pole::create(['nom' => 'À supprimer']);

    Livewire::test(Gestion::class)
        ->call('supprimerPole', $pole->id)
        ->assertHasNoErrors();

    expect(Pole::find($pole->id))->toBeNull();
});

it('supprime une catégorie', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Recherche de salle']);

    Livewire::test(Gestion::class)
        ->call('supprimerCategorie', $cat->id)
        ->assertHasNoErrors();

    expect(Categorie::find($cat->id))->toBeNull();
});

it('renomme un pôle', function () {
    $pole = Pole::create(['nom' => 'Pranaa']);

    Livewire::test(Gestion::class)
        ->call('editerPole', $pole->id)
        ->assertSet('poleEditNom', 'Pranaa')
        ->set('poleEditNom', 'Prana')
        ->call('renommerPole')
        ->assertHasNoErrors();

    expect(Pole::find($pole->id)->nom)->toBe('Prana');
});

it('refuse de renommer un pôle avec un nom déjà pris', function () {
    Pole::create(['nom' => 'Prana']);
    $digital = Pole::create(['nom' => 'Digital']);

    Livewire::test(Gestion::class)
        ->call('editerPole', $digital->id)
        ->set('poleEditNom', 'Prana')
        ->call('renommerPole')
        ->assertHasErrors(['poleEditNom']);

    expect(Pole::find($digital->id)->nom)->toBe('Digital');
});

it('renomme une catégorie', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation prof']);

    Livewire::test(Gestion::class)
        ->call('editerCategorie', $cat->id)
        ->set('categorieEditNom', 'Formation profs')
        ->call('renommerCategorie')
        ->assertHasNoErrors();

    expect(Categorie::find($cat->id)->nom)->toBe('Formation profs');
});

it('exige une connexion pour accéder à l’organisation', function () {
    auth()->logout();
    $this->get(route('organisation.index'))->assertRedirect(route('login'));
});
