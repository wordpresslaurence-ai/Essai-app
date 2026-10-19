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
