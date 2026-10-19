<?php

use App\Livewire\Reglages\Assistant;
use App\Services\Email\LecteurEmails;
use App\Services\Email\LecteurFactice;
use App\Services\Email\MessageEmail;
use App\Models\User;
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
    $lecteur = new LecteurFactice([
        messageFactice('a', '2026-01-01'),
        messageFactice('b', '2026-06-01'),
    ]);

    expect($lecteur->recupererDepuis(null))->toHaveCount(2);
    expect($lecteur->recupererDepuis(Carbon::parse('2026-03-01')))->toHaveCount(1);
});

it('produit un extrait nettoyé du corps', function () {
    $m = messageFactice('a', '2026-01-01');
    expect($m->extrait())->toBe('Un message avec des espaces');
});

it('le bouton Tester la connexion signale un succès', function () {
    $this->actingAs(User::factory()->create());
    $this->app->bind(LecteurEmails::class, fn () => new LecteurFactice([], connexionOk: true));

    Livewire::test(Assistant::class)
        ->call('testerConnexion')
        ->assertHasNoErrors();

    expect(session('message'))->toContain('réussie');
});

it('le bouton Tester la connexion signale un échec proprement', function () {
    $this->actingAs(User::factory()->create());
    $this->app->bind(LecteurEmails::class, fn () => new LecteurFactice([], connexionOk: false));

    Livewire::test(Assistant::class)
        ->call('testerConnexion');

    expect(session('erreur'))->toContain('échouée');
});
