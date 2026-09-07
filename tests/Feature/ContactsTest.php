<?php

use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Contacts\Formulaire;
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

it('crée une personne et une entreprise, visibles dans la liste', function () {
    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Durand')
        ->set('prenom', 'Marie')
        ->call('enregistrer');

    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_ENTREPRISE)
        ->set('nom', 'ACME SPRL')
        ->call('enregistrer');

    expect(Contact::where('nom', 'Durand')->exists())->toBeTrue();
    expect(Contact::where('nom', 'ACME SPRL')->exists())->toBeTrue();

    Livewire::test(Liste::class)
        ->assertSee('Durand')
        ->assertSee('ACME SPRL');
});

it('rattache une personne à une entreprise et l\'affiche sur la fiche entreprise', function () {
    $entreprise = Contact::factory()->entreprise()->create(['nom' => 'ACME SPRL']);

    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Durand')
        ->set('prenom', 'Marie')
        ->set('entreprise_id', (string) $entreprise->id)
        ->call('enregistrer');

    $personne = Contact::where('nom', 'Durand')->first();
    expect($personne->entreprise_id)->toBe($entreprise->id);

    Livewire::test(FicheDetail::class, ['contact' => $entreprise])
        ->assertSee('Durand');
});

it('détache les personnes quand leur entreprise est supprimée (jamais supprimées)', function () {
    $entreprise = Contact::factory()->entreprise()->create();
    $personne = Contact::factory()->create(['entreprise_id' => $entreprise->id]);

    Livewire::test(FicheDetail::class, ['contact' => $entreprise])
        ->call('supprimer');

    expect(Contact::find($entreprise->id))->toBeNull();      // entreprise supprimée
    expect(Contact::find($personne->id))->not->toBeNull();   // personne conservée
    expect($personne->fresh()->entreprise_id)->toBeNull();   // et détachée
});

it('recherche et filtre par type', function () {
    Contact::factory()->create(['nom' => 'Durand', 'prenom' => 'Marie']);
    Contact::factory()->entreprise()->create(['nom' => 'ACME SPRL']);

    // Recherche
    Livewire::test(Liste::class)
        ->set('recherche', 'Durand')
        ->assertSee('Durand')
        ->assertDontSee('ACME SPRL');

    // Filtre par type entreprise
    Livewire::test(Liste::class)
        ->set('type', Contact::TYPE_ENTREPRISE)
        ->assertSee('ACME SPRL')
        ->assertDontSee('Durand');
});

it('archive puis désarchive un contact', function () {
    $contact = Contact::factory()->create(['nom' => 'Durand']);

    $composant = Livewire::test(FicheDetail::class, ['contact' => $contact]);

    $composant->call('archiver');
    expect($contact->fresh()->estArchive())->toBeTrue();

    // N'apparaît plus dans la liste des actifs
    Livewire::test(Liste::class)->assertDontSee('Durand');
    // Mais bien dans les archivés
    Livewire::test(Liste::class)->set('statut', 'archives')->assertSee('Durand');

    $composant->call('desarchiver');
    expect($contact->fresh()->estArchive())->toBeFalse();
});

it('importe un CSV en ignorant les lignes invalides', function () {
    $csv = "nom,email,type\n".
        "Durand,marie@exemple.be,personne\n".
        ",invalide,personne\n".            // nom manquant -> ignorée
        "ACME,contact@acme.be,entreprise\n";

    $fichier = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->assertSet('etape', 'mapping')
        ->call('importer')
        ->assertSet('etape', 'termine')
        ->assertSet('rapport.importes', 2);

    expect(Contact::where('nom', 'Durand')->exists())->toBeTrue();
    expect(Contact::where('nom', 'ACME')->where('type', Contact::TYPE_ENTREPRISE)->exists())->toBeTrue();
    expect(Contact::count())->toBe(2); // la ligne sans nom a été ignorée
});
