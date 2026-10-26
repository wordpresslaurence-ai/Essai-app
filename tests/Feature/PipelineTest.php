<?php

use App\Livewire\Pipeline\Formulaire;
use App\Livewire\Pipeline\Tableau;
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

it('affiche un lien vers la fiche du contact dans le formulaire opportunité', function () {
    $contact = Contact::factory()->create();
    $op = Opportunite::create(['titre' => 'Test', 'contact_id' => $contact->id, 'etape' => Opportunite::ETAPE_NOUVEAU]);

    Livewire::test(Formulaire::class, ['opportunite' => $op])
        ->assertSee('Voir la fiche du contact');
});

it('pré-remplit titre, contact, catégorie et montant depuis la fiche d’un contact', function () {
    $pole = Pole::create(['nom' => 'Digital']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Site web', 'prix' => 1200]);
    $contact = Contact::factory()->create(['nom' => 'Durand', 'prenom' => 'Marie', 'categorie_id' => $cat->id]);

    Livewire::withQueryParams(['contact' => $contact->id])
        ->test(Formulaire::class)
        ->assertSet('titre', 'Opportunité — Marie Durand')
        ->assertSet('contact_id', (string) $contact->id)
        ->assertSet('categorie_id', (string) $cat->id)
        ->assertSet('montant', '1200.00');
});

it('pré-remplit le montant avec le prix de la catégorie choisie', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation', 'prix' => 950]);

    Livewire::test(Formulaire::class)
        ->set('categorie_id', (string) $cat->id)
        ->assertSet('montant', '950.00');
});

it('n’écrase pas un montant déjà saisi quand on choisit une catégorie', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation', 'prix' => 950]);

    Livewire::test(Formulaire::class)
        ->set('montant', '500')
        ->set('categorie_id', (string) $cat->id)
        ->assertSet('montant', '500');
});

it('initialise la date de création au jour même et l’enregistre', function () {
    Livewire::test(Formulaire::class)
        ->assertSet('date_creation', now()->toDateString())
        ->set('titre', 'Avec date')
        ->call('enregistrer');

    $op = Opportunite::where('titre', 'Avec date')->firstOrFail();
    expect($op->date_creation?->toDateString())->toBe(now()->toDateString());
});

it('enregistre le rôle de l’opportunité', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Partenariat aura')
        ->set('role', 'partenaire')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Opportunite::where('titre', 'Partenariat aura')->firstOrFail()->role)->toBe('partenaire');
});

it('refuse un rôle hors liste', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Mauvais rôle')
        ->set('role', 'nimporte')
        ->call('enregistrer')
        ->assertHasErrors(['role']);
});

it('filtre le pipeline par rôle', function () {
    $c = Contact::factory()->create();
    Opportunite::create(['titre' => 'DealPartenaire', 'contact_id' => $c->id, 'role' => 'partenaire', 'etape' => Opportunite::ETAPE_NOUVEAU]);
    Opportunite::create(['titre' => 'DealParticipant', 'contact_id' => $c->id, 'role' => 'participant', 'etape' => Opportunite::ETAPE_NOUVEAU]);

    Livewire::test(Tableau::class)
        ->set('role', 'partenaire')
        ->assertSee('DealPartenaire')
        ->assertDontSee('DealParticipant');
});
