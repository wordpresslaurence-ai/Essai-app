<?php

use App\Livewire\Contacts\FicheDetail;
use App\Models\BoiteEmail;
use App\Models\Contact;
use App\Models\Email;
use App\Models\User;
use App\Services\Email\LecteurEmails;
use App\Services\Email\LecteurFactice;
use App\Services\Email\MessageEmail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('retrouve sur la fiche les mails envoyés avant la création du contact', function () {
    BoiteEmail::factory()->create(['email' => 'laurence@homeharmony.be', 'active' => true]);
    $this->app->bind(LecteurEmails::class, fn () => new LecteurFactice([
        new MessageEmail(messageId: '<envoye@hh>', expediteur: 'laurence@homeharmony.be', destinataires: ['contact@salon.be'],
            objet: 'Une proposition', texte: 'Bonjour', date: Carbon::parse('2026-06-01 10:00:00'), filId: '<envoye@hh>'),
        new MessageEmail(messageId: '<recu@salon>', expediteur: 'Contact@Salon.be', destinataires: ['laurence@homeharmony.be'],
            objet: 'Re: Une proposition', texte: 'Merci', date: Carbon::parse('2026-06-03 10:00:00'), filId: '<envoye@hh>'),
        new MessageEmail(messageId: '<autre@x>', expediteur: 'quelquun@autre.be', destinataires: ['laurence@homeharmony.be'],
            objet: 'Sans rapport', texte: '…', date: Carbon::parse('2026-06-02 10:00:00'), filId: '<autre@x>'),
    ]));
    $contact = Contact::factory()->create(['email' => 'contact@salon.be']);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('Retrouver ses mails')
        ->call('retrouverMails')
        ->assertSee('2 mail(s) retrouvé(s)');

    $mails = $contact->emails()->orderBy('date_email')->get();
    expect($mails->pluck('direction')->all())->toBe([Email::DIRECTION_SORTANT, Email::DIRECTION_ENTRANT]);
    expect($contact->fresh()->dernierEnvoi()->format('d/m/Y'))->toBe('01/06/2026');

    // Une seconde recherche n'ajoute rien.
    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('retrouverMails')
        ->assertSee('Aucun nouveau mail trouvé');
    expect(Email::count())->toBe(2);
});
