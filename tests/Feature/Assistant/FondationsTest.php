<?php

use App\Models\Analyse;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Parametre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('crée une ligne unique de réglages via instance()', function () {
    $a = Parametre::instance();
    $b = Parametre::instance();

    expect($a->id)->toBe($b->id);
    expect(Parametre::count())->toBe(1);
});

it('chiffre les secrets au repos et les redonne en clair via le modèle', function () {
    $p = Parametre::instance();
    $p->update([
        'gmail_email' => 'laurencehomeharmony@gmail.com',
        'gmail_app_password' => 'secret-gmail',
        'anthropic_api_key' => 'sk-ant-secret',
    ]);

    // Lecture via le modèle : déchiffré
    expect($p->fresh()->gmail_app_password)->toBe('secret-gmail');
    expect($p->fresh()->anthropic_api_key)->toBe('sk-ant-secret');

    // Lecture brute en base : jamais en clair
    $brut = DB::table('parametres')->where('id', $p->id)->first();
    expect($brut->gmail_app_password)->not->toContain('secret-gmail');
    expect($brut->anthropic_api_key)->not->toContain('sk-ant-secret');
});

it('a un délai de relance par défaut de 5 jours', function () {
    expect(Parametre::instance()->relance_delai_jours)->toBe(5);
});

it('indique si l’assistant est configuré', function () {
    $p = Parametre::instance();
    expect($p->estConfigure())->toBeFalse();

    $p->update([
        'gmail_email' => 'x@gmail.com',
        'gmail_app_password' => 'p',
        'anthropic_api_key' => 'k',
    ]);
    expect($p->fresh()->estConfigure())->toBeTrue();
});

it('relie un email et une analyse à un contact', function () {
    $contact = Contact::factory()->create();

    $email = Email::create([
        'contact_id' => $contact->id,
        'direction' => Email::DIRECTION_ENTRANT,
        'de' => 'client@example.com',
        'a' => 'laurencehomeharmony@gmail.com',
        'objet' => 'Bonjour',
        'extrait' => 'Un message',
        'date_email' => now(),
        'message_id' => 'abc-123',
        'boite' => 'laurencehomeharmony@gmail.com',
    ]);

    $analyse = Analyse::create([
        'contact_id' => $contact->id,
        'resume' => 'résumé',
        'verdict' => 'relancer',
        'faite_le' => now(),
    ]);

    expect($contact->emails)->toHaveCount(1);
    expect($contact->analyses)->toHaveCount(1);
    expect($email->contact->is($contact))->toBeTrue();
    expect($email->estEntrant())->toBeTrue();
    expect($analyse->contact->is($contact))->toBeTrue();
});

it('empêche deux emails avec le même message_id', function () {
    $contact = Contact::factory()->create();
    $base = [
        'contact_id' => $contact->id,
        'direction' => Email::DIRECTION_ENTRANT,
        'de' => 'c@example.com',
        'a' => 'x@gmail.com',
        'date_email' => now(),
        'message_id' => 'doublon-1',
        'boite' => 'x@gmail.com',
    ];
    Email::create($base);

    expect(fn () => Email::create($base))->toThrow(Illuminate\Database\QueryException::class);
});
