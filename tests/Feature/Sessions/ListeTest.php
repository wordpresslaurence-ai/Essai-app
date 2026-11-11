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

it('affiche l’agenda annuel avec un point cliquable par session', function () {
    $prana = Pole::create(['nom' => 'Prana']);
    $formation = Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs', 'couleur' => '#aa3366']);
    $annee = (int) now()->year;

    $stage = Seance::create(['titre' => 'Stage de mai', 'categorie_id' => $formation->id, 'date_debut' => "$annee-05-07 09:00:00"]);
    $autre = Seance::create(['titre' => 'Même jour', 'date_debut' => "$annee-05-07 14:00:00"]);
    $anProchain = Seance::create(['titre' => 'An prochain', 'date_debut' => ($annee + 1).'-01-09 09:00:00']);
    Seance::create(['titre' => 'Sans date']);

    $composant = Livewire::test(Liste::class)->set('onglet', 'agenda');
    $agenda = $composant->viewData('agenda');

    expect($agenda['mois'])->toHaveCount(12);
    expect($agenda['total'])->toBe(2);
    expect($agenda['sansDate'])->toBe(1);

    // Mai : le 7 porte les deux sessions, chacune avec sa couleur.
    $mai = $agenda['mois'][4];
    expect($mai['jours'])->toHaveCount(31);
    expect(array_column($mai['jours'][6]['seances'], 'titre'))->toBe(['Stage de mai', 'Même jour']);
    expect($mai['jours'][6]['seances'][0]['couleur'])->toBe('#aa3366');
    expect($mai['jours'][5]['seances'])->toBe([]);

    $composant->assertSee(route('sessions.fiche', $stage), false)
        ->assertSee(route('sessions.fiche', $autre), false)
        ->assertSee('Stage de mai')                                   // titre au survol du point
        ->assertDontSee(route('sessions.fiche', $anProchain), false)
        ->assertSee('1 sans date');

    // Année suivante, puis retour.
    $composant->call('anneeSuivante')
        ->assertSee(route('sessions.fiche', $anProchain), false)
        ->assertDontSee(route('sessions.fiche', $stage), false)
        ->call('anneeCourante')
        ->assertSee(route('sessions.fiche', $stage), false);
});

it('applique le filtre de pôle à l’agenda', function () {
    $prana = Pole::create(['nom' => 'Prana']);
    $digital = Pole::create(['nom' => 'Digital']);
    $formation = Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs']);
    $sites = Categorie::create(['pole_id' => $digital->id, 'nom' => 'Sites web']);
    $annee = (int) now()->year;
    $a = Seance::create(['titre' => 'SessionPrana', 'categorie_id' => $formation->id, 'date_debut' => "$annee-03-10 09:00:00"]);
    $b = Seance::create(['titre' => 'SessionDigital', 'categorie_id' => $sites->id, 'date_debut' => "$annee-03-11 09:00:00"]);

    Livewire::test(Liste::class)
        ->set('onglet', 'agenda')
        ->set('pole', (string) $prana->id)
        ->assertSee(route('sessions.fiche', $a), false)
        ->assertDontSee(route('sessions.fiche', $b), false);
});
