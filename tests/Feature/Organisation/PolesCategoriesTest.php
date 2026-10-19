<?php

use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\Pole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée un pôle avec des catégories ordonnées', function () {
    $prana = Pole::create(['nom' => 'Prana', 'couleur' => '#4E8577']);
    Categorie::create(['pole_id' => $prana->id, 'nom' => 'Recherche de salle', 'ordre' => 2]);
    Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs', 'ordre' => 1]);

    expect($prana->categories)->toHaveCount(2);
    expect($prana->categories->first()->nom)->toBe('Formation profs'); // ordre 1 avant 2
});

it('rattache un contact à une catégorie et en déduit le pôle', function () {
    $digital = Pole::create(['nom' => 'Digital']);
    $sites = Categorie::create(['pole_id' => $digital->id, 'nom' => 'Sites web']);

    $contact = Contact::factory()->create(['categorie_id' => $sites->id]);

    expect($contact->categorie->is($sites))->toBeTrue();
    expect($contact->categorie->pole->nom)->toBe('Digital');
    expect($sites->contacts)->toHaveCount(1);
});

it('rattache une opportunité à une catégorie', function () {
    $prana = Pole::create(['nom' => 'Prana']);
    $journee = Categorie::create(['pole_id' => $prana->id, 'nom' => 'Journée aura']);
    $contact = Contact::factory()->create();

    $opp = Opportunite::create([
        'titre' => 'Journée du 12', 'contact_id' => $contact->id,
        'categorie_id' => $journee->id, 'montant' => 90, 'etape' => Opportunite::ETAPE_NOUVEAU,
    ]);

    expect($opp->categorie->libelleComplet())->toBe('Prana · Journée aura');
});

it('détache (sans supprimer) les contacts quand la catégorie est supprimée', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation profs']);
    $contact = Contact::factory()->create(['categorie_id' => $cat->id]);

    $cat->delete();

    expect(Contact::find($contact->id))->not->toBeNull();
    expect(Contact::find($contact->id)->categorie_id)->toBeNull();
});

it('supprime les catégories quand le pôle est supprimé mais garde les contacts', function () {
    $pole = Pole::create(['nom' => 'Digital']);
    $cat = Categorie::create(['pole_id' => $pole->id, 'nom' => 'PDF interactifs']);
    $contact = Contact::factory()->create(['categorie_id' => $cat->id]);

    $pole->delete();

    expect(Categorie::count())->toBe(0);
    expect(Contact::find($contact->id))->not->toBeNull();
    expect(Contact::find($contact->id)->categorie_id)->toBeNull();
});

it('empêche deux catégories de même nom dans un pôle', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation profs']);

    expect(fn () => Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation profs']))
        ->toThrow(Illuminate\Database\QueryException::class);
});
