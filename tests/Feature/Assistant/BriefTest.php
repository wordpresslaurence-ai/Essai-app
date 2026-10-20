<?php

use App\Livewire\Assistant\Brief;
use App\Models\Contact;
use App\Models\Email;
use App\Models\User;
use App\Services\IA\AssistantFactice;
use App\Services\IA\AssistantIA;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function emailBrief(Contact $c, string $direction, string $date): void
{
    Email::create([
        'contact_id' => $c->id,
        'direction' => $direction,
        'de' => 'x@example.com', 'a' => 'y@example.com',
        'objet' => 'Sujet', 'extrait' => 'Aperçu',
        'date_email' => Carbon::parse($date),
        'message_id' => uniqid('m', true),
        'boite' => 'laurence@gmail.com',
    ]);
}

it('affiche les réponses dues et les contacts à relancer', function () {
    $marie = Contact::factory()->create(['nom' => 'Dubois', 'prenom' => 'Marie']);
    emailBrief($marie, Email::DIRECTION_ENTRANT, Carbon::now()->subDay()->toDateString());

    $thomas = Contact::factory()->create(['nom' => 'Lambert', 'prenom' => 'Thomas']);
    emailBrief($thomas, Email::DIRECTION_SORTANT, Carbon::now()->subDays(10)->toDateString());

    Livewire::test(Brief::class)
        ->assertOk()
        ->assertSee('Marie Dubois')   // réponse due
        ->assertSee('Thomas Lambert'); // à relancer
});

it('génère des suggestions à la demande via l’assistant', function () {
    $this->app->bind(AssistantIA::class, fn () => new AssistantFactice(
        suggestions: ['Relancer Marie Dubois', 'Proposer une date de journée aura']
    ));

    Livewire::test(Brief::class)
        ->assertSet('suggestionsChargees', false)
        ->call('genererSuggestions')
        ->assertSet('suggestionsChargees', true)
        ->assertSee('Relancer Marie Dubois')
        ->assertSee('Proposer une date de journée aura');
});

it('exige une connexion pour accéder au brief', function () {
    auth()->logout();
    $this->get(route('assistant.brief'))->assertRedirect(route('login'));
});

it('crée une activité « Répondre » depuis le brief, sans doublon', function () {
    $marie = Contact::factory()->create(['nom' => 'Dubois', 'prenom' => 'Marie']);
    emailBrief($marie, Email::DIRECTION_ENTRANT, Carbon::now()->subDay()->toDateString());

    $c = Livewire::test(Brief::class);
    $c->call('creerActivite', $marie->id, 'repondre');

    expect(App\Models\Activite::where('contact_id', $marie->id)
        ->where('titre', 'Répondre à Marie Dubois')->count())->toBe(1);

    // Un second appel ne crée pas de doublon.
    $c->call('creerActivite', $marie->id, 'repondre');
    expect(App\Models\Activite::where('contact_id', $marie->id)
        ->where('titre', 'like', 'Répondre à %')->count())->toBe(1);
});

it('distingue les intentions « Répondre » et « Relancer » pour un même contact', function () {
    $contact = Contact::factory()->create(['nom' => 'Lambert', 'prenom' => 'Thomas']);

    $c = Livewire::test(Brief::class);
    $c->call('creerActivite', $contact->id, 'repondre');
    $c->call('creerActivite', $contact->id, 'relancer');

    expect(App\Models\Activite::where('contact_id', $contact->id)->count())->toBe(2);
});

it('affiche « Déjà dans tes activités » quand la tâche existe déjà', function () {
    $marie = Contact::factory()->create(['nom' => 'Dubois', 'prenom' => 'Marie']);
    emailBrief($marie, Email::DIRECTION_ENTRANT, Carbon::now()->subDay()->toDateString());
    App\Models\Activite::create([
        'titre' => 'Répondre à Marie Dubois', 'type' => App\Models\Activite::TYPE_TACHE,
        'contact_id' => $marie->id,
    ]);

    Livewire::test(Brief::class)->assertSee('✓ Activité');
});
