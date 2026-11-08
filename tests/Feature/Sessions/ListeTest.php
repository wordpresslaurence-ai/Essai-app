<?php

use App\Livewire\Sessions\Liste;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Pole;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('affiche les sessions à venir et compte les places', function () {
    $seance = Seance::create(['titre' => 'Promo mars', 'capacite' => 2, 'date_debut' => now()->addWeek()]);
    foreach (Contact::factory()->count(3)->create() as $c) {
        $seance->inscrire($c); // 2 confirmés + 1 en attente
    }

    Livewire::test(Liste::class)
        ->assertOk()
        ->assertSee('Promo mars')
        ->assertSee('Complet')
        ->assertSee('1 en attente');
});

it('sépare À venir et Passées', function () {
    Seance::create(['titre' => 'DéjàFaite', 'date_debut' => now()->subWeek()]);
    Seance::create(['titre' => 'ÀVenirBientôt', 'date_debut' => now()->addWeek()]);

    Livewire::test(Liste::class)
        ->assertSee('ÀVenirBientôt')
        ->assertDontSee('DéjàFaite')
        ->set('onglet', 'passees')
        ->assertSee('DéjàFaite')
        ->assertDontSee('ÀVenirBientôt');
});

it('filtre les sessions par pôle', function () {
    $prana = Pole::create(['nom' => 'Prana']);
    $digital = Pole::create(['nom' => 'Digital']);
    $formation = Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs']);
    $sites = Categorie::create(['pole_id' => $digital->id, 'nom' => 'Sites web']);

    Seance::create(['titre' => 'SessionPrana', 'categorie_id' => $formation->id, 'date_debut' => now()->addWeek()]);
    Seance::create(['titre' => 'SessionDigital', 'categorie_id' => $sites->id, 'date_debut' => now()->addWeek()]);

    Livewire::test(Liste::class)
        ->set('pole', (string) $prana->id)
        ->assertSee('SessionPrana')
        ->assertDontSee('SessionDigital');
});

it('exige une connexion pour voir les sessions', function () {
    auth()->logout();
    $this->get(route('sessions.index'))->assertRedirect(route('login'));
});

it('pagine la liste des sessions (20 par page)', function () {
    for ($i = 1; $i <= 25; $i++) {
        Seance::create(['titre' => 'Séance '.$i, 'date_debut' => now()->addDays($i)]);
    }

    $seances = Livewire::test(Liste::class)->viewData('seances');

    expect($seances->total())->toBe(25);
    expect($seances->count())->toBe(20);
});

it('affiche le contact du lieu (nom + ville) sur la carte de session', function () {
    $salle = App\Models\Contact::factory()->create(['nom' => 'Salle Zen', 'adresse_ville' => 'Namur']);
    Seance::create(['titre' => 'Stage', 'date_debut' => now()->addWeek(), 'lieu_contact_id' => $salle->id]);

    Livewire::test(Liste::class)
        ->assertSee('Salle Zen')
        ->assertSee('Namur');
});
