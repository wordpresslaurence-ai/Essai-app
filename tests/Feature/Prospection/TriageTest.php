<?php

use App\Livewire\Prospection\Resultats;
use App\Models\CandidatProspection;
use App\Models\Contact;
use App\Models\RechercheProspection;
use App\Models\User;
use App\Services\Prospection\Dedoublonnage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->recherche = RechercheProspection::create(['nom' => 'Test', 'nombre' => 10]);
});

function candidat(RechercheProspection $r, array $donnees): CandidatProspection
{
    return CandidatProspection::create([
        'recherche_prospection_id' => $r->id,
        'donnees' => $donnees,
        'nom' => $donnees['nom'] ?? null,
        'email' => isset($donnees['email']) ? mb_strtolower($donnees['email']) : null,
        'url_profil' => $donnees['url_profil'] ?? null,
    ]);
}

it('marque « déjà dans le CRM » un candidat dont l’e-mail existe au carnet', function () {
    $contact = Contact::factory()->create(['nom' => 'Zen', 'email' => 'contact@zen.be']);
    $c = candidat($this->recherche, ['nom' => 'Studio Zen', 'email' => 'CONTACT@zen.be']);

    $map = app(Dedoublonnage::class)->pour(collect([$c]));

    expect($map[$c->id]['type'])->toBe('crm');
    expect($map[$c->id]['contact_id'])->toBe($contact->id);
});

it('reconnaît aussi une adresse e-mail secondaire du carnet', function () {
    $contact = Contact::factory()->create(['nom' => 'Zen', 'email' => 'principal@zen.be']);
    $contact->adressesEmail()->create(['adresse' => 'secondaire@zen.be']);
    $c = candidat($this->recherche, ['nom' => 'Studio Zen', 'email' => 'secondaire@zen.be']);

    $map = app(Dedoublonnage::class)->pour(collect([$c]));
    expect($map[$c->id]['type'])->toBe('crm');
});

it('signale un doublon potentiel entre deux candidats de même e-mail', function () {
    $a = candidat($this->recherche, ['nom' => 'A', 'email' => 'x@y.be']);
    $b = candidat($this->recherche, ['nom' => 'B', 'email' => 'x@y.be']);

    $map = app(Dedoublonnage::class)->pour(collect([$a, $b]));
    expect($map[$a->id]['type'])->toBe('liste');
    expect($map[$b->id]['type'])->toBe('liste');
});

it('alerte « même adresse » entre une structure et une personne au même lieu', function () {
    // Une personne existe au carnet à cette adresse (e-mail différent).
    Contact::factory()->create([
        'nom' => 'Hermans', 'prenom' => 'Eve', 'email' => 'eve@perso.be',
        'adresse_rue' => 'Chaussée d’Alsemberg 786', 'adresse_code_postal' => '1420', 'adresse_ville' => 'Braine-l’Alleud',
    ]);
    // Le candidat est la structure, e-mail différent, même adresse.
    $c = candidat($this->recherche, [
        'nom' => 'Feeling Better', 'email' => 'info@feelingbetter.be',
        'adresse_rue' => 'Chaussée d’Alsemberg 786', 'adresse_code_postal' => '1420', 'adresse_ville' => 'Braine-l’Alleud',
    ]);

    $map = app(Dedoublonnage::class)->pour(collect([$c]));

    expect($map[$c->id]['type'])->toBe('nouveau');                 // pas un doublon
    expect($map[$c->id]['adresse_partagee'] ?? null)->toContain('Eve'); // mais alerté
});

it('signale un candidat déjà repéré dans une autre recherche (même e-mail)', function () {
    $autreRecherche = RechercheProspection::create(['nom' => 'Précédente', 'nombre' => 10]);
    candidat($autreRecherche, ['nom' => 'Feeling Better (ancien)', 'email' => 'info@feelingbetter.be']);

    $c = candidat($this->recherche, ['nom' => 'Feeling Better', 'email' => 'info@feelingbetter.be']);

    $map = app(Dedoublonnage::class)->pour(collect([$c]));

    expect($map[$c->id]['type'])->toBe('nouveau');                        // pas au carnet
    expect($map[$c->id]['deja_repere'] ?? null)->toContain('Feeling Better (ancien)'); // mais déjà repéré
});

it('marque « nouveau » un candidat sans correspondance', function () {
    $c = candidat($this->recherche, ['nom' => 'Neuf', 'email' => 'neuf@y.be']);
    $map = app(Dedoublonnage::class)->pour(collect([$c]));
    expect($map[$c->id]['type'])->toBe('nouveau');
});

it('rapproche par URL de profil normalisée (http/www/slash)', function () {
    Contact::factory()->create(['nom' => 'Insta', 'email' => null, 'url_profil' => 'instagram.com/marie']);
    $c = candidat($this->recherche, ['nom' => 'Marie', 'url_profil' => 'https://www.instagram.com/marie/']);

    $map = app(Dedoublonnage::class)->pour(collect([$c]));
    expect($map[$c->id]['type'])->toBe('crm');
});

it('sélectionne les visibles (actifs uniquement) et exclut les écartés', function () {
    candidat($this->recherche, ['nom' => 'A']);
    candidat($this->recherche, ['nom' => 'B']);
    $ecarte = candidat($this->recherche, ['nom' => 'C']);
    $ecarte->update(['ecarte' => true]);

    $composant = Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->call('basculerTout');

    expect($composant->get('selection'))->toHaveCount(2);
});

it('écarte puis restaure un candidat', function () {
    $c = candidat($this->recherche, ['nom' => 'ZorroCandidat']);

    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->call('ecarter', $c->id)
        ->assertDontSee('ZorroCandidat')               // masqué par défaut
        ->set('voirEcartes', true)
        ->assertSee('ZorroCandidat')
        ->call('restaurer', $c->id);

    expect($c->refresh()->ecarte)->toBeFalse();
});

it('affiche la fiche détaillée des 40 champs', function () {
    $c = candidat($this->recherche, ['nom' => 'Zen', 'fait_observe' => 'Loue une salle de 70 m2']);

    Livewire::test(Resultats::class, ['recherche' => $this->recherche])
        ->call('voirDetail', $c->id)
        ->assertSee('Loue une salle de 70 m2')
        ->assertSee('Fait observé');
});
