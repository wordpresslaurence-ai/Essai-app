<?php

use App\Livewire\Prospection\Resultats;
use App\Models\CandidatProspection;
use App\Models\Contact;
use App\Models\RechercheProspection;
use App\Models\User;
use App\Services\Prospection\ImporteurCandidats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->recherche = RechercheProspection::create(['nom' => 'Test', 'nombre' => 10]);
});

function cand(RechercheProspection $r, array $donnees): CandidatProspection
{
    return CandidatProspection::create([
        'recherche_prospection_id' => $r->id,
        'donnees' => $donnees,
        'nom' => $donnees['nom'] ?? null,
        'email' => isset($donnees['email']) ? mb_strtolower($donnees['email']) : null,
        'url_profil' => $donnees['url_profil'] ?? null,
    ]);
}

it('crée un nouveau contact avec les champs mappés et les étiquettes', function () {
    $c = cand($this->recherche, [
        'nom' => 'Studio Zen', 'email' => 'contact@zen.be', 'type' => 'entreprise',
        'origine' => 'Recherche web', 'temperature' => 'À qualifier', 'priorite_prospection' => 'Haute',
        'fait_observe' => 'Loue une salle de 70 m2', 'etiquettes' => 'Prospect IA | À vérifier',
    ]);

    app(ImporteurCandidats::class)->importer($this->recherche, [$c->id]);

    $contact = Contact::where('nom', 'Studio Zen')->firstOrFail();
    expect($contact->type)->toBe(Contact::TYPE_ENTREPRISE);
    expect($contact->origine)->toBe('recherche_web');        // libellé -> clé
    expect($contact->priorite_prospection)->toBe('haute');
    expect($contact->fait_observe)->toBe('Loue une salle de 70 m2');
    expect($contact->etiquettes()->pluck('nom')->all())->toContain('Prospect IA', 'À vérifier');

    expect($c->refresh()->contact_id)->toBe($contact->id);   // lien + idempotence
    expect($c->importe_at)->not->toBeNull();
});

it('complète une fiche existante sans écraser, et ajoute aux notes', function () {
    $contact = Contact::factory()->create([
        'nom' => 'Zen', 'email' => 'contact@zen.be',
        'fonction' => 'Gérant',            // déjà rempli -> ne doit pas changer
        'notes' => 'Note existante',
        'telephone' => null,               // vide -> doit être rempli
    ]);

    $c = cand($this->recherche, [
        'nom' => 'Zen', 'email' => 'contact@zen.be',
        'fonction' => 'Autre fonction',    // ne doit PAS écraser
        'telephone' => '081 22 33 44',     // doit remplir le vide
        'fait_observe' => 'Loue une salle', 'notes' => 'Analyse IA',
    ]);

    app(ImporteurCandidats::class)->importer($this->recherche, [$c->id]);

    $contact->refresh();
    expect($contact->fonction)->toBe('Gérant');               // préservé
    expect($contact->telephone)->toBe('081 22 33 44');        // complété
    expect($contact->fait_observe)->toBe('Loue une salle');   // complété
    expect($contact->notes)->toContain('Note existante');     // conservée
    expect($contact->notes)->toContain('Analyse IA');         // ajoutée
    expect(Contact::where('nom', 'Zen')->count())->toBe(1);   // pas de doublon
});

it('préserve une opposition « ne plus contacter » existante', function () {
    $contact = Contact::factory()->create(['email' => 'x@y.be', 'ne_plus_contacter' => true]);
    $c = cand($this->recherche, ['nom' => 'X', 'email' => 'x@y.be', 'ne_plus_contacter' => 'non']);

    app(ImporteurCandidats::class)->importer($this->recherche, [$c->id]);

    expect($contact->refresh()->ne_plus_contacter)->toBeTrue(); // jamais retirée
});

it('ajoute l’e-mail du candidat en adresse secondaire s’il diffère', function () {
    $contact = Contact::factory()->create(['nom' => 'Zen', 'email' => 'principal@zen.be', 'url_profil' => 'zen.be/p']);
    $c = cand($this->recherche, ['nom' => 'Zen', 'email' => 'autre@zen.be', 'url_profil' => 'https://zen.be/p']);

    app(ImporteurCandidats::class)->importer($this->recherche, [$c->id]);

    expect($contact->refresh()->adressesEmail->pluck('adresse')->all())->toContain('autre@zen.be');
});

it('est idempotent : relancer l’import ne crée pas de doublon', function () {
    $c = cand($this->recherche, ['nom' => 'Zen', 'email' => 'contact@zen.be']);

    app(ImporteurCandidats::class)->importer($this->recherche, [$c->id]);
    $rapport = app(ImporteurCandidats::class)->importer($this->recherche, [$c->id]);

    expect(Contact::where('nom', 'Zen')->count())->toBe(1);
    expect($rapport['ignores'])->toBe(1); // déjà importé
});

it('ne crée qu’un contact pour deux candidats de même e-mail dans le lot', function () {
    $a = cand($this->recherche, ['nom' => 'Zen A', 'email' => 'contact@zen.be']);
    $b = cand($this->recherche, ['nom' => 'Zen B', 'email' => 'contact@zen.be']);

    $rapport = app(ImporteurCandidats::class)->importer($this->recherche, [$a->id, $b->id]);

    expect(Contact::where('email', 'contact@zen.be')->count())->toBe(1);
    expect($rapport['crees'])->toBe(1);
    expect($rapport['completes'])->toBe(1);
});

it('préparer l’import accepte les ids envoyés par le client', function () {
    $c = cand($this->recherche, ['nom' => 'Zen', 'email' => 'z@z.be']);

    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->call('preparerImport', [(string) $c->id])
        ->assertSet('confirmation', true)
        ->assertSet('selection', [(string) $c->id])
        ->assertSet('apercuImport.total', 1);
});

it('importe depuis le composant après confirmation', function () {
    $c = cand($this->recherche, ['nom' => 'Studio Zen', 'email' => 'contact@zen.be']);

    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->set('selection', [(string) $c->id])
        ->call('preparerImport')
        ->assertSet('confirmation', true)
        ->assertSet('apercuImport.total', 1)
        ->call('confirmerImport')
        ->assertSet('confirmation', false);

    expect(Contact::where('nom', 'Studio Zen')->exists())->toBeTrue();
});
