<?php

use App\Livewire\Prospection\Resultats;
use App\Models\CandidatProspection;
use App\Models\RechercheProspection;
use App\Models\User;
use App\Services\Prospection\AnalyseurCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->recherche = RechercheProspection::create(['nom' => 'Test', 'nombre' => 10]);
});

it('analyse un CSV (séparateur ;) et crée les candidats, en ignorant les lignes sans nom', function () {
    $csv = "nom;email;fonction;origine;url_profil\n".
        "Dupont;MARIE@exemple.be;Professeure;Instagram;instagram.com/marie\n".
        ";sansnom@exemple.be;;Web;\n".           // sans nom -> ignorée
        "Studio Zen;contact@zen.be;;Recherche web;\n";

    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->set('csv', $csv)
        ->call('analyser')
        ->assertHasNoErrors()
        ->assertSet('rapport.total', 2)
        ->assertSet('rapport.ignores', 1);

    $marie = CandidatProspection::where('nom', 'Dupont')->firstOrFail();
    expect($marie->email)->toBe('marie@exemple.be');          // dénormalisé + minuscule
    expect($marie->champ('fonction'))->toBe('Professeure');    // accès aux 40 champs
    expect($marie->recherche_prospection_id)->toBe($this->recherche->id);
    expect(CandidatProspection::count())->toBe(2);
});

it('est idempotent : recoller le même CSV ne cumule pas les candidats', function () {
    $csv = "nom;email\nDupont;marie@exemple.be\n";

    $c = Livewire::test(Resultats::class, ['recherche' => $this->recherche]);
    $c->set('csv', $csv)->call('analyser');
    $c->set('csv', $csv)->call('analyser');

    expect(CandidatProspection::count())->toBe(1);
});

it('reconnaît un collage séparé par des tabulations (copie depuis un tableau)', function () {
    $csv = "nom\temail\tfonction\n".
        "Dupont\tmarie@exemple.be\tProfesseure\n";

    $resultat = app(AnalyseurCsv::class)->analyser($csv);

    expect($resultat['total'])->toBe(1);
    expect($resultat['entetes_inconnues'])->toBe([]);         // tout est reconnu
    expect($resultat['candidats'][0]['email'])->toBe('marie@exemple.be');
});

it('analyse un fichier .csv déposé', function () {
    $csv = "nom;email\nDupont;marie@exemple.be\nStudio;contact@studio.be\n";
    $fichier = UploadedFile::fake()->createWithContent('resultats.csv', $csv);

    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->set('fichier', $fichier)
        ->call('analyserFichier')
        ->assertHasNoErrors()
        ->assertSet('rapport.total', 2);

    expect(CandidatProspection::where('nom', 'Dupont')->exists())->toBeTrue();
});

it('signale les en-têtes non reconnues sans bloquer', function () {
    $resultat = app(AnalyseurCsv::class)->analyser("nom;colonne_bidon\nDupont;valeur\n");

    expect($resultat['total'])->toBe(1);
    expect($resultat['entetes_inconnues'])->toBe(['colonne_bidon']);
});

it('gère un champ multi-ligne entre guillemets', function () {
    $csv = "nom;notes\n\"Dupont\";\"ligne 1\nligne 2\"\n";

    $resultat = app(AnalyseurCsv::class)->analyser($csv);

    expect($resultat['total'])->toBe(1);
    expect($resultat['candidats'][0]['notes'])->toContain('ligne 2');
});

it('refuse un collage vide', function () {
    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->set('csv', '')
        ->call('analyser')
        ->assertHasErrors(['csv']);
});

it('vide les candidats', function () {
    CandidatProspection::create(['recherche_prospection_id' => $this->recherche->id, 'donnees' => ['nom' => 'X'], 'nom' => 'X']);

    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->call('vider');

    expect(CandidatProspection::count())->toBe(0);
});
