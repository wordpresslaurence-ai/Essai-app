<?php

use App\Livewire\Reglages\BoitesEmail;
use App\Models\BoiteEmail;
use App\Models\User;
use App\Services\Email\LecteurEmails;
use App\Services\Email\LecteurFactice;
use App\Services\Email\MessageEmail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function messageFactice(string $id, string $date): MessageEmail
{
    return new MessageEmail(
        messageId: $id,
        expediteur: 'client@example.com',
        destinataires: ['laurencehomeharmony@gmail.com'],
        objet: 'Bonjour',
        texte: "Un  message   avec   des espaces",
        date: Carbon::parse($date),
    );
}

it('filtre les messages par date dans le lecteur factice', function () {
    $boite = BoiteEmail::factory()->create();
    $lecteur = new LecteurFactice([
        messageFactice('a', '2026-01-01'),
        messageFactice('b', '2026-06-01'),
    ]);

    expect($lecteur->recupererDepuis($boite, null))->toHaveCount(2);
    expect($lecteur->recupererDepuis($boite, Carbon::parse('2026-03-01')))->toHaveCount(1);
});

it('produit un extrait nettoyé du corps', function () {
    $m = messageFactice('a', '2026-01-01');
    expect($m->extrait())->toBe('Un message avec des espaces');
});

it('le bouton Tester la connexion signale un succès', function () {
    $this->actingAs(User::factory()->create());
    $boite = BoiteEmail::factory()->create(['libelle' => 'Gmail Prana']);
    $this->app->bind(LecteurEmails::class, fn () => new LecteurFactice([], connexionOk: true));

    Livewire::test(BoitesEmail::class)
        ->call('tester', $boite->id)
        ->assertSet('erreur', null)
        ->assertSet('message', 'Connexion réussie pour « Gmail Prana ».');
});

it('le bouton Tester la connexion signale un échec proprement', function () {
    $this->actingAs(User::factory()->create());
    $boite = BoiteEmail::factory()->create();
    $this->app->bind(LecteurEmails::class, fn () => new LecteurFactice([], connexionOk: false));

    Livewire::test(BoitesEmail::class)
        ->call('tester', $boite->id)
        ->assertSet('message', null)
        ->assertSet('erreur', 'Connexion factice échouée.');
});
