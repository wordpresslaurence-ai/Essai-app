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

it('clôture automatiquement une tâche « Répondre » quand le contact n\'est plus en réponse due', function () {
    $contact = Contact::factory()->create(); // aucun e-mail entrant -> pas de réponse due
    $tache = App\Models\Activite::create([
        'titre' => 'Répondre à '.$contact->nomComplet(),
        'type' => App\Models\Activite::TYPE_TACHE,
        'contact_id' => $contact->id,
    ]);

    Livewire::test(Brief::class); // mount -> clôture auto

    expect($tache->fresh()->terminee_at)->not->toBeNull();
});

it('ne clôture pas une tâche « Répondre » si le contact doit toujours une réponse', function () {
    $marie = Contact::factory()->create(['nom' => 'Dubois', 'prenom' => 'Marie']);
    emailBrief($marie, Email::DIRECTION_ENTRANT, Carbon::now()->subDay()->toDateString());
    $tache = App\Models\Activite::create([
        'titre' => 'Répondre à Marie Dubois', 'type' => App\Models\Activite::TYPE_TACHE, 'contact_id' => $marie->id,
    ]);

    Livewire::test(Brief::class);

    expect($tache->fresh()->terminee_at)->toBeNull();
});

it('affiche les opportunités qui stagnent et crée une relance', function () {
    $contact = Contact::factory()->create(['nom' => 'Durand', 'prenom' => 'Paul']);
    $opp = App\Models\Opportunite::create([
        'titre' => 'Refonte site', 'contact_id' => $contact->id, 'etape' => App\Models\Opportunite::ETAPE_QUALIFIE,
    ]);
    $opp->update(['etape_changee_le' => Carbon::now()->subDays(30)]);

    $c = Livewire::test(Brief::class)
        ->assertSee('Opportunités qui stagnent')
        ->assertSee('Refonte site');

    $c->call('creerTacheOpportunite', $opp->id);

    expect(App\Models\Activite::where('opportunite_id', $opp->id)
        ->where('titre', 'like', 'Relancer l’opportunité%')->count())->toBe(1);

    // Pas de doublon.
    $c->call('creerTacheOpportunite', $opp->id);
    expect(App\Models\Activite::where('opportunite_id', $opp->id)->count())->toBe(1);
});

it('affiche les leads chauds à réveiller', function () {
    $c = Contact::factory()->create(['nom' => 'Noël', 'prenom' => 'Lucie', 'temperature' => 'chaud']);
    Contact::where('id', $c->id)->update(['created_at' => Carbon::now()->subDays(40)]);

    Livewire::test(Brief::class)
        ->assertSee('À réveiller')
        ->assertSee('Lucie Noël');
});

it('applique l\'échéance choisie à la tâche créée', function () {
    $marie = Contact::factory()->create(['nom' => 'Dubois', 'prenom' => 'Marie']);
    emailBrief($marie, Email::DIRECTION_ENTRANT, Carbon::now()->subDay()->toDateString());

    $date = Carbon::now()->addDays(3)->toDateString();

    Livewire::test(Brief::class)
        ->set('echeanceReponse', $date)
        ->call('creerActivite', $marie->id, 'repondre');

    $t = App\Models\Activite::where('contact_id', $marie->id)->where('titre', 'like', 'Répondre à %')->first();
    expect($t->echeance->toDateString())->toBe($date);
});

it('le digest « depuis hier » compte mails récents, prospects à créer et activités du jour', function () {
    $c = App\Models\Contact::factory()->create(['email' => 'client@x.be']);
    emailBrief($c, Email::DIRECTION_ENTRANT, Carbon::now()->subHours(3)->toDateTimeString()); // récent
    emailBrief($c, Email::DIRECTION_ENTRANT, Carbon::now()->subDays(10)->toDateTimeString()); // trop vieux

    App\Models\Activite::create(['titre' => 'Créer le prospect : inconnu@x.be', 'type' => App\Models\Activite::TYPE_TACHE]);
    App\Models\Activite::create(['titre' => 'Rappeler', 'type' => App\Models\Activite::TYPE_TACHE, 'echeance' => now()->toDateString()]);

    $digest = Livewire::test(Brief::class)->instance()->digest();

    expect($digest['mailsRecents'])->toBe(1);
    expect($digest['prospects'])->toBe(1);
    expect($digest['activites'])->toBeGreaterThanOrEqual(1);
});
