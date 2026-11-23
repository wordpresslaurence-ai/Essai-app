<?php

use App\Livewire\Contacts\ImportCsv;
use App\Livewire\Contacts\Liste;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('bloque l’import quand le fichier contient des contacts déjà présents', function () {
    $existant = Contact::factory()->create(['prenom' => 'Stéphanie', 'nom' => 'Van den Heede', 'email' => 'stephanie@salon.be']);
    Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Sansmail', 'email' => null]);

    $csv = "prenom;nom;email\n"
        ."Stéphanie;Stéphanie Van den Heede;Stephanie@Salon.be\n" // même adresse (casse différente)
        ."Paul;Sansmail;\n"                                       // sans e-mail, même nom
        ."Anne;Nouvelle;anne@exemple.be\n"
        ."Anne;Copie;anne@exemple.be\n";                          // deux fois dans le fichier
    $fichier = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $page = Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->assertSee('Import bloqué')
        ->assertSee('adresse déjà dans le carnet')
        ->assertSee('même nom qu’une fiche existante')
        ->assertSee('même adresse que la ligne 4');

    expect(collect($page->instance()->doublons())->pluck('ligne')->all())->toBe([2, 3, 5]);
    expect($page->instance()->doublons()[0]['contact']->id)->toBe($existant->id);

    $page->call('importer')->assertHasErrors(['doublons'])->assertSet('etape', 'mapping');
    expect(Contact::count())->toBe(2);
});

it('importe normalement un fichier sans doublon', function () {
    Contact::factory()->create(['email' => 'deja@exemple.be']);
    $fichier = UploadedFile::fake()->createWithContent('contacts.csv', "prenom;nom;email\nAnne;Nouvelle;anne@exemple.be\n");

    Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->assertDontSee('Import bloqué')
        ->call('importer')
        ->assertSet('etape', 'termine')
        ->assertSet('rapport.importes', 1);
});

it('signale dans le carnet les fiches qui partagent une adresse e-mail et les montre côte à côte', function () {
    Contact::factory()->create(['prenom' => 'Stéphanie', 'nom' => 'Van den Heede', 'email' => 'stephanie@salon.be']);
    Contact::factory()->create(['prenom' => 'Stéphanie', 'nom' => 'Stéphanie Van den Heede', 'email' => 'Stephanie@salon.be']);
    Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Unique', 'email' => 'marie@exemple.be']);

    Livewire::test(Liste::class)
        ->assertSee('2 fiches partagent leur adresse e-mail')
        ->set('doublons', true)
        ->assertSee('Stéphanie Van den Heede')
        ->assertSee('Stéphanie Stéphanie Van den Heede')
        ->assertDontSee('Marie Unique')
        ->assertSee('Revenir au carnet complet');
});
