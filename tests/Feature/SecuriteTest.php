<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ajoute les en-têtes de sécurité sur les réponses web', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('les champs système ne sont pas assignables en masse', function () {
    // a_la_une, archived_at, newsletter_exporte_le ne doivent PAS être remplis
    // via une assignation de masse (fill), même si on les passe.
    $contact = new App\Models\Contact;
    $contact->fill(['nom' => 'X', 'a_la_une' => true, 'archived_at' => now()]);

    expect($contact->a_la_une)->not->toBeTrue();
    expect($contact->archived_at)->toBeNull();
});

it('archiver / à la une fonctionnent toujours via leurs méthodes', function () {
    $contact = App\Models\Contact::factory()->create();

    $contact->archiver();
    expect($contact->fresh()->archived_at)->not->toBeNull();

    $contact->desarchiver();
    expect($contact->fresh()->archived_at)->toBeNull();
});
