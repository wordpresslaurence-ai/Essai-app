<?php

use App\Livewire\Pipeline\Formulaire;
use App\Livewire\Pipeline\Tableau;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('crée une opportunité, visible dans la colonne Nouveau', function () {
    $contact = Contact::factory()->create();

    Livewire::test(Formulaire::class)
        ->set('titre', 'Nouveau projet')
        ->set('contact_id', (string) $contact->id)
        ->set('montant', '3500')
        ->call('enregistrer');

    $op = Opportunite::where('titre', 'Nouveau projet')->first();
    expect($op)->not->toBeNull();
    expect($op->etape)->toBe(Opportunite::ETAPE_NOUVEAU);

    Livewire::test(Tableau::class)->assertSee('Nouveau projet');
});

it('déplace une opportunité et enregistre la date de clôture à Gagné', function () {
    $op = Opportunite::factory()->create(['etape' => Opportunite::ETAPE_PROPOSITION]);

    Livewire::test(Tableau::class)
        ->call('deplacer', $op->id, Opportunite::ETAPE_GAGNE);

    $op->refresh();
    expect($op->etape)->toBe(Opportunite::ETAPE_GAGNE);
    expect($op->date_cloture)->not->toBeNull();
});

it('calcule le total par colonne', function () {
    Opportunite::factory()->create(['etape' => Opportunite::ETAPE_QUALIFIE, 'montant' => 1000]);
    Opportunite::factory()->create(['etape' => Opportunite::ETAPE_QUALIFIE, 'montant' => 500]);

    Livewire::test(Tableau::class)->assertSee('1 500 €');
});

it('masque les opportunités perdues par défaut et les affiche via la bascule', function () {
    Opportunite::factory()->create(['titre' => 'Deal perdu', 'etape' => Opportunite::ETAPE_PERDU]);

    Livewire::test(Tableau::class)
        ->assertDontSee('Deal perdu')
        ->set('voirPerdus', true)
        ->assertSee('Deal perdu');
});

it('supprime une opportunité', function () {
    $op = Opportunite::factory()->create();

    Livewire::test(Formulaire::class, ['opportunite' => $op])
        ->call('supprimer');

    expect(Opportunite::find($op->id))->toBeNull();
});

it('conserve une opportunité quand son contact est supprimé (détachée)', function () {
    $contact = Contact::factory()->create();
    $op = Opportunite::factory()->create(['contact_id' => $contact->id]);

    $contact->delete();

    expect(Opportunite::find($op->id))->not->toBeNull();
    expect($op->fresh()->contact_id)->toBeNull();
});
