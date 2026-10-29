<?php

use App\Livewire\Assistant\MailsRecents;
use App\Models\Contact;
use App\Models\Email;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function creerMail(Contact $c, string $direction, string $objet, string $date): Email
{
    return Email::create([
        'contact_id' => $c->id, 'direction' => $direction, 'de' => 'x@y.be', 'a' => 'z@y.be',
        'objet' => $objet, 'extrait' => 'extrait', 'date_email' => $date, 'message_id' => uniqid(),
        'boite' => 'test@homeharmony.be',
    ]);
}

it('liste les mails du plus récent au plus ancien, tous contacts confondus', function () {
    $marie = Contact::factory()->create(['nom' => 'Durand', 'prenom' => 'Marie']);
    $zen = Contact::factory()->entreprise()->create(['nom' => 'Studio Zen']);
    creerMail($marie, 'entrant', 'Ancien mail', '2026-01-01 10:00');
    creerMail($zen, 'sortant', 'Mail récent', '2026-10-01 10:00');

    Livewire::test(MailsRecents::class)
        ->assertSee('Mail récent')
        ->assertSee('Ancien mail')
        ->assertSeeInOrder(['Mail récent', 'Ancien mail']); // tri décroissant
});

it('filtre par sens', function () {
    $c = Contact::factory()->create(['nom' => 'Durand']);
    creerMail($c, 'entrant', 'Un reçu', '2026-10-01 10:00');
    creerMail($c, 'sortant', 'Un envoyé', '2026-10-02 10:00');

    Livewire::test(MailsRecents::class)
        ->set('sens', 'entrant')
        ->assertSee('Un reçu')
        ->assertDontSee('Un envoyé');
});

it('recherche par objet', function () {
    $c = Contact::factory()->create(['nom' => 'Durand']);
    creerMail($c, 'entrant', 'Devis atelier', '2026-10-01 10:00');
    creerMail($c, 'entrant', 'Autre sujet', '2026-10-02 10:00');

    Livewire::test(MailsRecents::class)
        ->set('recherche', 'Devis')
        ->assertSee('Devis atelier')
        ->assertDontSee('Autre sujet');
});
