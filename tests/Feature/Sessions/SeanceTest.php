<?php

use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Seance;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('confirme tant qu\'il reste de la place, puis met en liste d\'attente', function () {
    $seance = Seance::create(['titre' => 'Promo mars', 'capacite' => 10]);

    $contacts = Contact::factory()->count(12)->create();
    foreach ($contacts as $c) {
        $seance->inscrire($c);
    }

    expect($seance->placesPrises())->toBe(10);
    expect($seance->placesRestantes())->toBe(0);
    expect($seance->estComplete())->toBeTrue();
    expect($seance->inscriptions()->where('statut', Inscription::STATUT_ATTENTE)->count())->toBe(2);
});

it('ne crée pas de doublon d\'inscription active', function () {
    $seance = Seance::create(['titre' => 'Journée aura', 'capacite' => 5]);
    $contact = Contact::factory()->create();

    $seance->inscrire($contact);
    $seance->inscrire($contact);

    expect($seance->inscriptions()->count())->toBe(1);
});

it('sans capacité, tout le monde est confirmé', function () {
    $seance = Seance::create(['titre' => 'Atelier libre', 'capacite' => null]);

    foreach (Contact::factory()->count(30)->create() as $c) {
        $seance->inscrire($c);
    }

    expect($seance->placesRestantes())->toBeNull();
    expect($seance->estComplete())->toBeFalse();
    expect($seance->inscriptions()->where('statut', Inscription::STATUT_CONFIRME)->count())->toBe(30);
});

it('promeut la plus ancienne personne en attente quand une place se libère', function () {
    $seance = Seance::create(['titre' => 'Promo', 'capacite' => 2]);
    [$a, $b, $c] = Contact::factory()->count(3)->create()->all();

    $insA = $seance->inscrire($a); // confirmé
    $seance->inscrire($b);          // confirmé
    $insC = $seance->inscrire($c);  // attente

    expect($insC->statut)->toBe(Inscription::STATUT_ATTENTE);

    // Une place se libère
    $insA->update(['statut' => Inscription::STATUT_ANNULE]);
    $seance->promouvoirSuivant();

    expect($insC->fresh()->statut)->toBe(Inscription::STATUT_CONFIRME);
});

it('supprime les inscriptions quand la séance est supprimée', function () {
    $seance = Seance::create(['titre' => 'Promo']);
    $seance->inscrire(Contact::factory()->create());

    $seance->delete();

    expect(Inscription::count())->toBe(0);
});

it('supprime les inscriptions d\'un contact supprimé, sans toucher aux autres', function () {
    $seance = Seance::create(['titre' => 'Promo', 'capacite' => 10]);
    $c1 = Contact::factory()->create();
    $c2 = Contact::factory()->create();
    $seance->inscrire($c1);
    $seance->inscrire($c2);

    $c1->delete();

    expect(Inscription::where('contact_id', $c1->id)->count())->toBe(0);
    expect(Inscription::where('contact_id', $c2->id)->count())->toBe(1);
});

it('filtre les séances à venir et passées', function () {
    Seance::create(['titre' => 'Passée', 'date_debut' => now()->subWeek()]);
    Seance::create(['titre' => 'Future', 'date_debut' => now()->addWeek()]);
    Seance::create(['titre' => 'Sans date']);

    expect(Seance::aVenir()->pluck('titre')->all())->toContain('Future', 'Sans date');
    expect(Seance::aVenir()->pluck('titre')->all())->not->toContain('Passée');
    expect(Seance::passees()->pluck('titre')->all())->toBe(['Passée']);
});
