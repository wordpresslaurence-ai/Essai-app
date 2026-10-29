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

it('pré-remplit l’entreprise à la création depuis la fiche entreprise (+ Ajouter une personne)', function () {
    $entreprise = Contact::factory()->entreprise()->create(['nom' => 'Feeling Better']);

    Livewire::withQueryParams(['entreprise' => $entreprise->id])
        ->test(Formulaire::class)
        ->assertSet('type', Contact::TYPE_PERSONNE)
        ->assertSet('entreprise_id', (string) $entreprise->id)
        ->set('nom', 'Hermans')
        ->set('prenom', 'Eve')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Contact::where('nom', 'Hermans')->first()->entreprise_id)->toBe($entreprise->id);
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

it('ne signale pas de faux doublons pour un contact sans e-mail ni entreprise', function () {
    Contact::factory()->create(['nom' => 'ContactExistant', 'email' => 'autre@exemple.be']);

    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'NouveauSansMail')
        ->set('email', '')
        ->assertDontSee('ContactExistant'); // aucun doublon affiché
});

it('signale un doublon quand l’e-mail existe déjà (insensible à la casse)', function () {
    Contact::factory()->create(['nom' => 'DejaLa', 'email' => 'dup@exemple.be']);

    Livewire::test(Formulaire::class)
        ->set('nom', 'Nouveau')
        ->set('email', 'DUP@exemple.be')
        ->assertSee('DejaLa');
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

it('importe les nouveaux champs : origine, expérience, discipline (clé ou libellé)', function () {
    $csv = "nom;origine;experience_yoga;discipline;fait_observe\n".
        "Nidra;Instagram;Professeurs de yoga;Yoga nidra;Anime des ateliers\n".
        "Keys;instagram;professeurs;Hatha yoga;\n";

    $fichier = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->call('importer')
        ->assertSet('rapport.importes', 2);

    $libelle = Contact::where('nom', 'Nidra')->firstOrFail();
    expect($libelle->origine)->toBe('instagram');            // libellé « Instagram » -> clé
    expect($libelle->experience_yoga)->toBe('professeurs');
    expect($libelle->discipline)->toBe('Yoga nidra');
    expect($libelle->fait_observe)->toBe('Anime des ateliers');

    $parCle = Contact::where('nom', 'Keys')->firstOrFail();
    expect($parCle->origine)->toBe('instagram');             // clé directe acceptée aussi
});

it('ignore une origine hors liste sans bloquer l’import', function () {
    $csv = "nom;origine\nTest;ValeurBidon\n";
    $fichier = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->call('importer')
        ->assertSet('rapport.importes', 1);

    expect(Contact::where('nom', 'Test')->firstOrFail()->origine)->toBeNull();
});

it('rattache la catégorie (par nom) et les étiquettes à l’import', function () {
    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $cat = App\Models\Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation profs de yoga']);

    $csv = "nom;categorie;etiquettes\n".
        "Marie;formation profs de yoga;Prospect|Intéressé formation profs\n";

    $fichier = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->call('importer')
        ->assertSet('rapport.importes', 1);

    $contact = Contact::where('nom', 'Marie')->firstOrFail();
    expect($contact->categorie_id)->toBe($cat->id);          // catégorie résolue par nom
    expect($contact->etiquettes()->pluck('nom')->sort()->values()->all())
        ->toBe(['Intéressé formation profs', 'Prospect']);    // étiquettes créées + rattachées
});

it('importe les champs de prospection (priorité, date, ne plus contacter)', function () {
    $csv = "nom;region;linkedin;priorite_prospection;date_verification;ne_plus_contacter\n".
        "Alpha;Wallonie;linkedin.com/in/a;Haute;15/10/2026;oui\n".
        "Beta;;;;;non\n";

    $fichier = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->call('importer')
        ->assertSet('rapport.importes', 2);

    $a = Contact::where('nom', 'Alpha')->firstOrFail();
    expect($a->region)->toBe('Wallonie');
    expect($a->linkedin)->toBe('linkedin.com/in/a');
    expect($a->priorite_prospection)->toBe('haute');       // « Haute » -> clé
    expect($a->date_verification?->format('Y-m-d'))->toBe('2026-10-15'); // JJ/MM/AAAA -> date
    expect($a->ne_plus_contacter)->toBeTrue();

    expect(Contact::where('nom', 'Beta')->firstOrFail()->ne_plus_contacter)->toBeFalse();
});

it('exclut « ne plus contacter » de l’export newsletter', function () {
    $joignable = Contact::factory()->create(['nom' => 'Joignable', 'email' => 'ok@x.be', 'ne_plus_contacter' => false]);
    $exclu = Contact::factory()->create(['nom' => 'Exclu', 'email' => 'non@x.be', 'ne_plus_contacter' => true]);

    // L'export marque les contacts ciblés comme exportés : l'exclu ne doit pas l'être.
    Livewire::test(App\Livewire\Contacts\ExportNewsletter::class)->call('exporter');

    expect($joignable->refresh()->newsletter_exporte_le)->not->toBeNull();
    expect($exclu->refresh()->newsletter_exporte_le)->toBeNull();
});

it('reconnaît des intitulés de colonnes façon Odoo', function () {
    // En-têtes typiques d'un export Odoo (res.partner).
    $csv = "Name,Email,Phone,Job Position,Street,Zip,City,Country,VAT\n".
        "Studio Zen,contact@zen.be,+32 81 22,Gérant,Rue Zen 1,5000,Namur,BE,BE0403.170.701\n";

    $fichier = UploadedFile::fake()->createWithContent('odoo.csv', $csv);

    Livewire::test(ImportCsv::class)
        ->set('fichier', $fichier)
        ->call('importer')
        ->assertSet('rapport.importes', 1);

    $contact = Contact::where('nom', 'Studio Zen')->firstOrFail();
    expect($contact->email)->toBe('contact@zen.be');
    expect($contact->fonction)->toBe('Gérant');              // « Job Position » -> fonction
    expect($contact->adresse_code_postal)->toBe('5000');     // « Zip » -> code postal
    expect($contact->adresse_ville)->toBe('Namur');
    expect($contact->numero_tva)->toBe('BE0403.170.701');    // « VAT » -> n° TVA
});

it('enregistre et affiche le lien du compte / profil', function () {
    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Durand')
        ->set('origine', 'facebook')
        ->set('url_profil', 'facebook.com/marie.durand')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $contact = Contact::where('nom', 'Durand')->first();
    expect($contact->url_profil)->toBe('facebook.com/marie.durand');
    expect($contact->urlProfilHref())->toBe('https://facebook.com/marie.durand');

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('facebook.com/marie.durand');
});

it('pré-remplit et devine une fiche depuis une tâche « Créer le prospect »', function () {
    $tache = App\Models\Activite::create([
        'titre' => 'Créer le prospect : jean@studio.be',
        'type' => App\Models\Activite::TYPE_TACHE,
        'notes' => "Objet du mail : Demande\n--- Message ---\nBonjour, Jean Testeur, Studio Factice.",
    ]);
    app()->instance(App\Services\IA\AssistantIA::class, new App\Services\IA\AssistantFactice());

    Livewire::withQueryParams(['prospect' => $tache->id])
        ->test(Formulaire::class)
        ->assertSet('type', Contact::TYPE_PERSONNE)
        ->assertSet('email', 'jean@studio.be')
        ->call('devinerDepuisMail')
        ->assertSet('prenom', 'Jean')
        ->assertSet('nom', 'Testeur')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Contact::where('nom', 'Testeur')->exists())->toBeTrue();
    expect($tache->fresh()->estTerminee())->toBeTrue(); // tâche clôturée à la création
});
