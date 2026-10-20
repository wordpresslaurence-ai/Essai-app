<?php

use App\Livewire\Contacts\Liste;
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

    $this->prana = Pole::create(['nom' => 'Prana']);
    $this->digital = Pole::create(['nom' => 'Digital']);
    $this->formation = Categorie::create(['pole_id' => $this->prana->id, 'nom' => 'Formation profs']);
    $this->aura = Categorie::create(['pole_id' => $this->prana->id, 'nom' => 'Journée aura']);
    $this->sites = Categorie::create(['pole_id' => $this->digital->id, 'nom' => 'Sites web']);
});

it('filtre la liste des contacts par pôle', function () {
    Contact::factory()->create(['nom' => 'ContactPrana', 'categorie_id' => $this->formation->id]);
    Contact::factory()->create(['nom' => 'ContactDigital', 'categorie_id' => $this->sites->id]);

    Livewire::test(Liste::class)
        ->set('pole', (string) $this->prana->id)
        ->assertSee('ContactPrana')
        ->assertDontSee('ContactDigital');
});

it('filtre la liste des contacts par catégorie', function () {
    Contact::factory()->create(['nom' => 'ContactFormation', 'categorie_id' => $this->formation->id]);
    Contact::factory()->create(['nom' => 'ContactAura', 'categorie_id' => $this->aura->id]);

    Livewire::test(Liste::class)
        ->set('pole', (string) $this->prana->id)
        ->set('categorie', (string) $this->formation->id)
        ->assertSee('ContactFormation')
        ->assertDontSee('ContactAura');
});

it('réinitialise la catégorie quand on change de pôle', function () {
    Livewire::test(Liste::class)
        ->set('pole', (string) $this->prana->id)
        ->set('categorie', (string) $this->formation->id)
        ->set('pole', (string) $this->digital->id)
        ->assertSet('categorie', '');
});

it('filtre le pipeline par pôle', function () {
    $c1 = Contact::factory()->create();
    $c2 = Contact::factory()->create();
    Opportunite::create(['titre' => 'OppPrana', 'contact_id' => $c1->id, 'categorie_id' => $this->formation->id, 'etape' => Opportunite::ETAPE_NOUVEAU]);
    Opportunite::create(['titre' => 'OppDigital', 'contact_id' => $c2->id, 'categorie_id' => $this->sites->id, 'etape' => Opportunite::ETAPE_NOUVEAU]);

    Livewire::test(Tableau::class)
        ->set('pole', (string) $this->prana->id)
        ->assertSee('OppPrana')
        ->assertDontSee('OppDigital');
});

it('filtre le pipeline par catégorie', function () {
    $c1 = Contact::factory()->create();
    Opportunite::create(['titre' => 'OppFormation', 'contact_id' => $c1->id, 'categorie_id' => $this->formation->id, 'etape' => Opportunite::ETAPE_NOUVEAU]);
    Opportunite::create(['titre' => 'OppAura', 'contact_id' => $c1->id, 'categorie_id' => $this->aura->id, 'etape' => Opportunite::ETAPE_NOUVEAU]);

    Livewire::test(Tableau::class)
        ->set('pole', (string) $this->prana->id)
        ->set('categorie', (string) $this->formation->id)
        ->assertSee('OppFormation')
        ->assertDontSee('OppAura');
});

it('affiche tout quand aucun pôle n’est choisi', function () {
    Contact::factory()->create(['nom' => 'ContactPrana', 'categorie_id' => $this->formation->id]);
    Contact::factory()->create(['nom' => 'ContactDigital', 'categorie_id' => $this->sites->id]);

    Livewire::test(Liste::class)
        ->assertSee('ContactPrana')
        ->assertSee('ContactDigital');
});
