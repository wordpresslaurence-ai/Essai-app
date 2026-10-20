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

it('enregistre les réglages et chiffre les secrets', function () {
    Livewire::test(Assistant::class)
        ->set('gmail_email', 'laurencehomeharmony@gmail.com')
        ->set('gmail_app_password', 'mon-mot-de-passe-app')
        ->set('anthropic_api_key', 'sk-ant-xxx')
        ->set('relance_delai_jours', 7)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $p = Parametre::instance();
    expect($p->gmail_email)->toBe('laurencehomeharmony@gmail.com');
    expect($p->gmail_app_password)->toBe('mon-mot-de-passe-app'); // déchiffré via le modèle
    expect($p->anthropic_api_key)->toBe('sk-ant-xxx');
    expect($p->relance_delai_jours)->toBe(7);
});

it('ne réaffiche jamais les secrets et les conserve si on laisse vide', function () {
    // Pré-enregistre des secrets
    Parametre::instance()->update([
        'gmail_app_password' => 'secret-initial',
        'anthropic_api_key' => 'cle-initiale',
    ]);

    Livewire::test(Assistant::class)
        // Les champs secrets restent vides à l'affichage
        ->assertSet('gmail_app_password', '')
        ->assertSet('anthropic_api_key', '')
        // Les indicateurs « déjà défini » sont vrais
        ->assertSet('gmailMotDePasseDefini', true)
        ->assertSet('cleApiDefinie', true)
        // On modifie autre chose sans toucher aux secrets
        ->set('gmail_email', 'x@gmail.com')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $p = Parametre::instance();
    expect($p->gmail_app_password)->toBe('secret-initial'); // conservé
    expect($p->anthropic_api_key)->toBe('cle-initiale');    // conservé
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
