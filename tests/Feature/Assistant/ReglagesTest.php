<?php

use App\Livewire\Reglages\Assistant;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('affiche l’écran des réglages avec les liens pré-remplis', function () {
    Livewire::test(Assistant::class)
        ->assertOk()
        ->assertSet('relance_delai_jours', 5)
        ->assertSet('lien_seance_decouverte', 'https://homeharmony.be/seance-prana-gratuite/')
        ->assertSet('lien_journee_aura', 'https://homeharmony.be/nettoyage-aura-chakras');
});

it('enregistre les réglages et chiffre la clé API', function () {
    Livewire::test(Assistant::class)
        ->set('anthropic_api_key', 'sk-ant-xxx')
        ->set('relance_delai_jours', 7)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $p = Parametre::instance();
    expect($p->anthropic_api_key)->toBe('sk-ant-xxx'); // déchiffré via le modèle
    expect($p->relance_delai_jours)->toBe(7);
});

it('ne réaffiche jamais la clé API et la conserve si on laisse vide', function () {
    // Pré-enregistre le secret
    Parametre::instance()->update([
        'anthropic_api_key' => 'cle-initiale',
    ]);

    Livewire::test(Assistant::class)
        // Le champ secret reste vide à l'affichage
        ->assertSet('anthropic_api_key', '')
        // L'indicateur « déjà définie » est vrai
        ->assertSet('cleApiDefinie', true)
        // On modifie autre chose sans toucher au secret
        ->set('pitch_prospection', 'Un contexte')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Parametre::instance()->anthropic_api_key)->toBe('cle-initiale'); // conservé
});

it('valide le délai de relance', function () {
    Livewire::test(Assistant::class)
        ->set('relance_delai_jours', 0)
        ->call('enregistrer')
        ->assertHasErrors(['relance_delai_jours']);
});

it('exige une connexion pour accéder aux réglages', function () {
    auth()->logout();
    $this->get(route('reglages.assistant'))->assertRedirect(route('login'));
});

it('enregistre le pitch de prospection', function () {
    Livewire::test(Assistant::class)
        ->set('pitch_prospection', 'Je suis Laurence, je forme des profs de yoga Prana Vidya.')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Parametre::instance()->pitch_prospection)->toBe('Je suis Laurence, je forme des profs de yoga Prana Vidya.');
});

it('enregistre l’offre et les liens par catégorie (sans limite de taille)', function () {
    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $cat = App\Models\Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation profs']);

    $pageDeVente = str_repeat('Description longue de mon offre. ', 400); // > 5000 caractères

    Livewire::test(Assistant::class)
        ->set("offres.{$cat->id}", $pageDeVente)
        ->call('ajouterLien', $cat->id)
        ->set("liens.{$cat->id}.0.libelle", 'Programme')
        ->set("liens.{$cat->id}.0.url", 'https://exemple.be/formation-prana')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $cat->refresh();
    expect(mb_strlen((string) $cat->offre))->toBeGreaterThan(5000);
    expect($cat->liens)->toBe([['libelle' => 'Programme', 'url' => 'https://exemple.be/formation-prana']]);
});

it('retire un lien d’une catégorie', function () {
    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $cat = App\Models\Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation profs']);

    Livewire::test(Assistant::class)
        ->call('ajouterLien', $cat->id)
        ->set("liens.{$cat->id}.0.url", 'https://exemple.be/a')
        ->call('ajouterLien', $cat->id)
        ->set("liens.{$cat->id}.1.url", 'https://exemple.be/b')
        ->call('supprimerLien', $cat->id, 0)
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect($cat->fresh()->liens)->toBe([['libelle' => '', 'url' => 'https://exemple.be/b']]);
});
