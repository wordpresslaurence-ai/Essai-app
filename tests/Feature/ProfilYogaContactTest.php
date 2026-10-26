<?php

use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Contacts\Formulaire;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Parametre;
use App\Models\Pole;
use App\Models\User;
use App\Services\IA\AssistantClaude;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('enregistre les champs profil yoga & origine du contact', function () {
    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Nidra')
        ->set('prenom', 'Claire')
        ->set('discipline', 'Hatha et yoga nidra')
        ->set('fait_observe', 'Enseigne le hatha et le yoga nidra ; propose des ateliers.')
        ->set('angle_contact', 'Explorer le prana en complément de son enseignement du nidra.')
        ->set('experience_yoga', 'professeurs')
        ->set('origine', 'recommandation')
        ->set('origine_precision', 'Recommandée par Sophie')
        ->call('enregistrer');

    $contact = Contact::where('nom', 'Nidra')->firstOrFail();

    expect($contact->discipline)->toBe('Hatha et yoga nidra');
    expect($contact->fait_observe)->toContain('ateliers');
    expect($contact->angle_contact)->toContain('prana');
    expect($contact->experience_yoga)->toBe('professeurs');
    expect($contact->libelleExperienceYoga())->toBe('Professeurs de yoga');
    expect($contact->origine)->toBe('recommandation');
    expect($contact->libelleOrigine())->toBe('Recommandation');
    expect($contact->origine_precision)->toBe('Recommandée par Sophie');
});

it('enregistre les champs de prospection et le drapeau « ne plus contacter »', function () {
    Livewire::test(Formulaire::class)
        ->set('type', Contact::TYPE_PERSONNE)
        ->set('nom', 'Prospect')
        ->set('region', 'Wallonie')
        ->set('linkedin', 'linkedin.com/in/x')
        ->set('langues', 'FR, EN')
        ->set('discipline_precision', 'aussi méditation')
        ->set('formats_proposes', 'ateliers')
        ->set('role_commercial', 'Partenaire')
        ->set('priorite_prospection', 'haute')
        ->set('besoin_exprime', 'formation')
        ->set('date_verification', '2026-10-15')
        ->set('ne_plus_contacter', true)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $c = Contact::where('nom', 'Prospect')->firstOrFail();
    expect($c->region)->toBe('Wallonie');
    expect($c->linkedin)->toBe('linkedin.com/in/x');
    expect($c->priorite_prospection)->toBe('haute');
    expect($c->libellePriorite())->toBe('Haute');
    expect($c->ne_plus_contacter)->toBeTrue();
    expect($c->date_verification?->format('Y-m-d'))->toBe('2026-10-15');
});

it('refuse une origine hors liste', function () {
    Livewire::test(Formulaire::class)
        ->set('nom', 'Test')
        ->set('origine', 'pas_une_origine')
        ->call('enregistrer')
        ->assertHasErrors('origine');
});

it('la prospection reprend le fait observé et l’angle de premier contact de la fiche', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test', 'pitch_prospection' => null]);

    $pole = Pole::create(['nom' => 'Prana']);
    $cat = Categorie::create([
        'pole_id' => $pole->id, 'nom' => 'Formation profs',
        'offre' => 'Formation de profs de yoga Prana Vidya.',
    ]);
    $contact = Contact::factory()->create([
        'categorie_id' => $cat->id,
        'fait_observe' => 'Enseigne le yoga nidra et anime des ateliers.',
        'angle_contact' => 'Explorer le prana en complément du nidra.',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['objet' => 'O', 'corps' => 'C Laurence'])]],
        ], 200),
    ]);

    // Sans angle saisi à la volée : l'angle de la fiche doit être utilisé.
    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('redigerProspection');

    Http::assertSent(function ($request) {
        $data = json_encode($request->data());

        return str_contains($data, 'yoga nidra et anime des ateliers') // fait observé
            && str_contains($data, 'Explorer le prana en compl');         // angle de premier contact
    });
});
