<?php

use App\Livewire\Contacts\FicheDetail;
use App\Models\Contact;
use App\Models\Email;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function emailPour(Contact $c, string $direction, string $objet, string $date): void
{
    Email::create([
        'contact_id' => $c->id,
        'direction' => $direction,
        'de' => 'x@example.com',
        'a' => 'y@example.com',
        'objet' => $objet,
        'extrait' => 'Aperçu du message',
        'date_email' => Carbon::parse($date),
        'message_id' => uniqid('m', true),
        'boite' => 'laurencehomeharmony@gmail.com',
    ]);
}

it('affiche le fil des e-mails du contact avec le sens reçu/envoyé', function () {
    $contact = Contact::factory()->create();
    emailPour($contact, Email::DIRECTION_ENTRANT, 'Première prise de contact', '2026-05-01');
    emailPour($contact, Email::DIRECTION_SORTANT, 'Ma réponse chaleureuse', '2026-05-03');

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertOk()
        ->assertSee('Première prise de contact')
        ->assertSee('Ma réponse chaleureuse')
        ->assertSee('Reçu')
        ->assertSee('Envoyé');
});

it('affiche un message quand le contact n’a aucun e-mail', function () {
    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('Aucun e-mail synchronisé');
});

it('supprime un e-mail de la fiche', function () {
    $contact = Contact::factory()->create();
    emailPour($contact, Email::DIRECTION_ENTRANT, 'À garder', '2026-05-01');
    emailPour($contact, Email::DIRECTION_ENTRANT, 'À supprimer', '2026-05-02');
    $aSupprimer = Email::where('objet', 'À supprimer')->firstOrFail();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('supprimerEmail', $aSupprimer->id);

    expect(Email::count())->toBe(1);
    expect(Email::first()->objet)->toBe('À garder');
});

it('ne supprime pas les e-mails d’un autre contact', function () {
    $contact = Contact::factory()->create();
    $autre = Contact::factory()->create();
    emailPour($contact, Email::DIRECTION_ENTRANT, 'Mien', '2026-05-01');
    emailPour($autre, Email::DIRECTION_ENTRANT, 'Pas touche', '2026-05-01');
    $emailAutre = Email::where('objet', 'Pas touche')->firstOrFail();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('supprimerEmail', $emailAutre->id); // id d'un autre contact

    expect(Email::whereKey($emailAutre->id)->exists())->toBeTrue(); // intact
});

it('vide tout l’historique e-mails du contact', function () {
    $contact = Contact::factory()->create();
    emailPour($contact, Email::DIRECTION_ENTRANT, 'Un', '2026-05-01');
    emailPour($contact, Email::DIRECTION_SORTANT, 'Deux', '2026-05-02');

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('viderEmails');

    expect(Email::where('contact_id', $contact->id)->count())->toBe(0);
});
