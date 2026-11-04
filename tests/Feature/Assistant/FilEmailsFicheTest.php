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

it('limite l’affichage à 6 e-mails avec un bouton « Voir tout »', function () {
    $contact = Contact::factory()->create();
    for ($i = 1; $i <= 8; $i++) {
        emailPour($contact, Email::DIRECTION_ENTRANT, 'Sujet'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), '2026-05-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    $c = Livewire::test(FicheDetail::class, ['contact' => $contact]);

    // 6 plus récents visibles, 2 plus anciens masqués.
    $c->assertSee('Sujet08')->assertSee('Sujet03')
        ->assertDontSee('Sujet01')->assertDontSee('Sujet02')
        ->assertSee('Voir tout (8)');

    $c->call('voirTousMails')
        ->assertSee('Sujet01')->assertSee('Sujet02');
});

it('ouvre un e-mail dans la modale avec son corps complet', function () {
    $contact = Contact::factory()->create();
    $mail = Email::create([
        'contact_id' => $contact->id, 'direction' => Email::DIRECTION_ENTRANT,
        'de' => 'client@example.com', 'a' => 'moi@example.com',
        'objet' => 'Question', 'extrait' => 'aperçu court',
        'corps' => 'Texte COMPLET du message, repère ZZZ999.',
        'date_email' => now(), 'message_id' => uniqid('m', true), 'boite' => 'moi@example.com',
    ]);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertDontSee('ZZZ999')                 // aperçu seulement dans la liste
        ->call('ouvrirMail', $mail->id)
        ->assertSet('mailOuvertId', $mail->id)
        ->assertSee('ZZZ999')                     // corps complet dans la modale
        ->call('fermerMail')
        ->assertSet('mailOuvertId', null);
});

it('n’ouvre pas dans la modale l’e-mail d’un autre contact', function () {
    $contact = Contact::factory()->create();
    $autre = Contact::factory()->create();
    emailPour($autre, Email::DIRECTION_ENTRANT, 'Ailleurs', '2026-05-01');
    $mailAutre = Email::where('objet', 'Ailleurs')->firstOrFail();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('ouvrirMail', $mailAutre->id)
        ->assertSet('mailOuvertId', null);
});
