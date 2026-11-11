<?php

use App\Livewire\Reglages\ReservationsSite;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Invitation;
use App\Models\Parametre;
use App\Models\ReservationRecue;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Parametre::instance()->update(['webhook_jeton' => 'jeton-de-test']);
});

/** Corps « toutes les données » tel que l'envoie FluentBooking (créneau en UTC). */
function reservationSite(array $booking = [], string $titre = 'Séance découverte'): array
{
    return [
        'booking' => $booking + [
            'id' => 100,
            'first_name' => 'Marie',
            'last_name' => 'Durand',
            'email' => 'Marie.Durand@exemple.be',
            'phone' => '+32 470 12 34 56',
            'start_time' => '2026-12-13 09:30:00', // 10 h 30 à Bruxelles
            'person_time_zone' => 'Europe/Brussels',
            'status' => 'scheduled',
        ],
        'calendar_event' => ['title' => $titre],
    ];
}

function adresseWebhook(string $jeton = 'jeton-de-test', array $query = []): string
{
    return route('webhooks.reservations', ['jeton' => $jeton] + $query);
}

it('refuse un jeton faux, et toute adresse tant qu’aucun jeton n’existe', function () {
    $this->postJson(adresseWebhook('mauvais'), reservationSite())->assertNotFound();

    Parametre::instance()->update(['webhook_jeton' => null]);
    $this->postJson(adresseWebhook('jeton-de-test'), reservationSite())->assertNotFound();

    expect(Contact::count())->toBe(0);
});

it('crée le contact et l’inscrit à la session du même jour', function () {
    $seance = Seance::create(['titre' => 'Séance découverte', 'date_debut' => '2026-12-13 10:30:00']);

    $this->postJson(adresseWebhook(), reservationSite())
        ->assertOk()
        ->assertJson(['ok' => true, 'statut' => ReservationRecue::STATUT_INSCRIT]);

    $contact = Contact::firstOrFail();
    expect($contact->email)->toBe('marie.durand@exemple.be');
    expect($contact->prenom)->toBe('Marie');
    expect($contact->nom)->toBe('Durand');
    expect($contact->origine)->toBe('site_formulaire');

    expect($seance->inscriptions()->where('contact_id', $contact->id)->value('statut'))
        ->toBe(Inscription::STATUT_CONFIRME);
    expect(Activite::where('contact_id', $contact->id)->value('titre'))
        ->toBe('Inscription via le site : Séance découverte');
    expect(ReservationRecue::first()->seance_id)->toBe($seance->id);
});

it('ne crée ni doublon de contact ni doublon d’inscription', function () {
    $seance = Seance::create(['titre' => 'Séance découverte', 'date_debut' => '2026-12-13 10:30:00']);
    $existant = Contact::factory()->create(['email' => 'autre@exemple.be', 'telephone' => null]);
    $existant->adressesEmail()->create(['adresse' => 'marie.durand@exemple.be']); // adresse secondaire
    Invitation::create(['seance_id' => $seance->id, 'contact_id' => $existant->id]);

    $this->postJson(adresseWebhook(), reservationSite())->assertOk();
    $this->postJson(adresseWebhook(), reservationSite())->assertOk(); // même réservation renvoyée

    expect(Contact::count())->toBe(1);
    expect($existant->fresh()->telephone)->toBe('+32 470 12 34 56'); // téléphone complété
    expect($seance->inscriptions()->count())->toBe(1);
    expect(Activite::where('contact_id', $existant->id)->count())->toBe(1);
});

it('note un rendez-vous quand aucune session n’a lieu ce jour-là', function () {
    $this->postJson(adresseWebhook(), reservationSite([], 'Appel visio'))
        ->assertOk()
        ->assertJson(['statut' => ReservationRecue::STATUT_RDV]);
    $this->postJson(adresseWebhook(), reservationSite([], 'Appel visio'))->assertOk(); // pas de doublon

    $contact = Contact::firstOrFail();
    $rdv = Activite::where('contact_id', $contact->id)->get();

    expect($rdv)->toHaveCount(1);
    expect($rdv->first()->type)->toBe(Activite::TYPE_RDV);
    expect($rdv->first()->titre)->toBe('Rendez-vous réservé : Appel visio');
    expect($rdv->first()->echeance->format('Y-m-d H:i'))->toBe('2026-12-13 10:30'); // heure de Bruxelles
    expect($rdv->first()->estTerminee())->toBeFalse();
});

it('annule l’inscription quand la réservation est annulée', function () {
    $seance = Seance::create(['titre' => 'Séance découverte', 'date_debut' => '2026-12-13 10:30:00']);

    $this->postJson(adresseWebhook(), reservationSite())->assertOk();
    $this->postJson(adresseWebhook(), reservationSite(['status' => 'cancelled']))
        ->assertOk()
        ->assertJson(['statut' => ReservationRecue::STATUT_ANNULE]);

    expect($seance->inscriptions()->value('statut'))->toBe(Inscription::STATUT_ANNULE);
});

it('accepte des champs à plat et une session imposée par l’adresse', function () {
    $seance = Seance::create(['titre' => 'Stage de mai', 'date_debut' => '2027-04-30 09:00:00']);

    $this->postJson(adresseWebhook(query: ['seance' => $seance->id]), [
        'name' => 'Paul Petit Jean',
        'guest_email' => 'paul@exemple.fr',
    ])->assertOk()->assertJson(['statut' => ReservationRecue::STATUT_INSCRIT]);

    $contact = Contact::firstOrFail();
    expect($contact->prenom)->toBe('Paul');
    expect($contact->nom)->toBe('Petit Jean');
    expect($seance->inscriptions()->where('contact_id', $contact->id)->exists())->toBeTrue();
});

it('ignore une réservation sans e-mail, mais la garde au journal', function () {
    $this->postJson(adresseWebhook(), ['booking' => ['first_name' => 'Anonyme']])
        ->assertOk()
        ->assertJson(['statut' => ReservationRecue::STATUT_IGNORE]);

    expect(Contact::count())->toBe(0);
    expect(ReservationRecue::first()->contenu)->toContain('Anonyme');
});

it('crée puis remplace l’adresse depuis les réglages, et affiche le journal', function () {
    $this->actingAs(User::factory()->create());
    Parametre::instance()->update(['webhook_jeton' => null]);

    $composant = Livewire::test(ReservationsSite::class)
        ->assertSee('Aucune adresse')
        ->call('generer');

    $jeton = Parametre::instance()->webhook_jeton;
    expect($jeton)->toHaveLength(40);
    $composant->assertSee($jeton);

    $this->postJson(adresseWebhook($jeton), reservationSite([], 'Appel visio'))->assertOk();

    Livewire::test(ReservationsSite::class)
        ->assertSee('Appel visio')
        ->call('basculerDetail', ReservationRecue::first()->id)
        ->assertSee('person_time_zone');

    auth()->logout();
    $this->get(route('reglages.reservations'))->assertRedirect(route('login'));
});

it('supprime une réservation et ce qu’elle avait créé, en gardant le contact', function () {
    $this->actingAs(User::factory()->create());
    $seance = Seance::create(['titre' => 'Séance découverte', 'date_debut' => '2026-12-13 10:30:00']);

    $this->postJson(adresseWebhook(), reservationSite())->assertOk();                                  // inscription
    $this->postJson(adresseWebhook(), reservationSite(['start_time' => '2026-12-20 09:30:00'], 'Appel visio'))->assertOk(); // rendez-vous

    $contact = Contact::firstOrFail();
    $autre = Activite::create(['titre' => 'Tâche à moi', 'type' => Activite::TYPE_TACHE, 'contact_id' => $contact->id]);
    expect(Activite::where('contact_id', $contact->id)->count())->toBe(3);

    $composant = Livewire::test(ReservationsSite::class);
    foreach (ReservationRecue::pluck('id') as $id) {
        $composant->call('supprimer', $id);
    }

    expect(ReservationRecue::count())->toBe(0);
    expect($seance->inscriptions()->count())->toBe(0);
    expect(Activite::where('contact_id', $contact->id)->pluck('id')->all())->toBe([$autre->id]); // la tâche manuelle reste
    expect(Contact::find($contact->id))->not->toBeNull();
});
