<?php

use App\Livewire\Prospection\Formulaire;
use App\Livewire\Prospection\Liste;
use App\Models\Categorie;
use App\Models\Pole;
use App\Models\RechercheProspection;
use App\Models\User;
use App\Services\Prospection\GenerateurBrief;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('crée une recherche et fige une copie de la description de l’offre', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create([
        'pole_id' => $pole->id, 'nom' => 'Journée aura et chakra',
        'offre' => 'Journée de nettoyage de l’aura et équilibrage des chakras.',
    ]);

    Livewire::test(Formulaire::class)
        ->set('nom', 'Profs yoga Bruxelles')
        ->set('categorie_id', (string) $cat->id)
        ->set('sources', ['web', 'instagram'])
        ->set('nombre', 8)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $r = RechercheProspection::where('nom', 'Profs yoga Bruxelles')->firstOrFail();
    expect($r->categorie_id)->toBe($cat->id);
    expect($r->offre_copie)->toContain('nettoyage de l’aura'); // copie figée
    expect($r->sources)->toBe(['web', 'instagram']);
    expect($r->nombre)->toBe(8);
});

it('exige un nom', function () {
    Livewire::test(Formulaire::class)
        ->set('nom', '')
        ->call('enregistrer')
        ->assertHasErrors(['nom' => 'required']);
});

it('le brief reprend l’offre, le nombre et le contrat CSV exact', function () {
    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create([
        'pole_id' => $pole->id, 'nom' => 'Journée aura et chakra',
        'offre' => 'Journée de nettoyage de l’aura.',
    ]);
    $r = RechercheProspection::create([
        'nom' => 'Test', 'categorie_id' => $cat->id, 'offre_copie' => $cat->offre,
        'sources' => ['web'], 'nombre' => 6,
    ]);

    $brief = app(GenerateurBrief::class)->generer($r);

    expect($brief)->toContain('Journée aura et chakra');     // l'offre
    expect($brief)->toContain('nettoyage de l’aura');        // sa description figée
    expect($brief)->toContain('6 (objectif');                // le nombre demandé
    expect($brief)->toContain(GenerateurBrief::ENTETES_CSV); // les en-têtes exacts
    expect($brief)->toContain('Prospect IA | À vérifier');   // l'étiquette imposée
});

it('les en-têtes du brief correspondent exactement à l’import', function () {
    // Même source de vérité que ImportCsv::champsDisponibles().
    $attendus = array_keys((new App\Livewire\Contacts\ImportCsv)->champsDisponibles());
    expect(explode(';', GenerateurBrief::ENTETES_CSV))->toBe($attendus);
});

it('affiche la recherche dans la liste et peut la supprimer', function () {
    $r = RechercheProspection::create(['nom' => 'À lister', 'nombre' => 10]);

    Livewire::test(Liste::class)
        ->assertSee('À lister')
        ->call('supprimer', $r->id);

    expect(RechercheProspection::find($r->id))->toBeNull();
});
