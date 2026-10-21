<?php

use App\Models\Contact;
use App\Models\Email;
use App\Models\Parametre;
use App\Services\Relances\CalculRelances;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dernierEmail(Contact $c, string $direction, string $date): void
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

it('classe en « réponse due » un contact dont le dernier message est reçu', function () {
    $c = Contact::factory()->create();
    dernierEmail($c, Email::DIRECTION_SORTANT, '2026-05-01'); // ancien envoi
    dernierEmail($c, Email::DIRECTION_ENTRANT, '2026-05-10'); // il a répondu après

    $calc = new CalculRelances(5);
    expect($calc->reponsesDues())->toHaveCount(1);
    expect($calc->aRelancer())->toHaveCount(0);
});

it('classe en « à relancer » un envoi sans réponse plus vieux que le délai', function () {
    $c = Contact::factory()->create();
    dernierEmail($c, Email::DIRECTION_SORTANT, Carbon::now()->subDays(10)->toDateString());

    $calc = new CalculRelances(5);
    expect($calc->aRelancer())->toHaveCount(1);
    expect($calc->reponsesDues())->toHaveCount(0);
});

it('ne relance pas un envoi plus récent que le délai', function () {
    $c = Contact::factory()->create();
    dernierEmail($c, Email::DIRECTION_SORTANT, Carbon::now()->subDays(2)->toDateString());

    expect((new CalculRelances(5))->aRelancer())->toHaveCount(0);
});

it('utilise le délai des réglages par défaut', function () {
    Parametre::instance()->update(['relance_delai_jours' => 3]);
    $c = Contact::factory()->create();
    dernierEmail($c, Email::DIRECTION_SORTANT, Carbon::now()->subDays(4)->toDateString());

    expect((new CalculRelances)->aRelancer())->toHaveCount(1);
});

it('ignore les contacts sans e-mail', function () {
    Contact::factory()->create(); // aucun e-mail

    $calc = new CalculRelances(5);
    expect($calc->reponsesDues())->toHaveCount(0);
    expect($calc->aRelancer())->toHaveCount(0);
});

it('filtre les relances par pôle quand un pôle est fourni', function () {
    $prana = App\Models\Pole::create(['nom' => 'Prana']);
    $digital = App\Models\Pole::create(['nom' => 'Digital']);
    $catPrana = App\Models\Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation']);
    $catDigital = App\Models\Categorie::create(['pole_id' => $digital->id, 'nom' => 'Sites']);

    $cPrana = Contact::factory()->create(['categorie_id' => $catPrana->id]);
    $cDigital = Contact::factory()->create(['categorie_id' => $catDigital->id]);
    dernierEmail($cPrana, Email::DIRECTION_ENTRANT, '2026-05-10');
    dernierEmail($cDigital, Email::DIRECTION_ENTRANT, '2026-05-10');

    // Tous pôles confondus : 2 réponses dues.
    expect((new CalculRelances(5))->reponsesDues())->toHaveCount(2);

    // Filtré sur Prana : une seule, celle du bon pôle.
    $filtre = new CalculRelances(5, $prana->id);
    expect($filtre->reponsesDues())->toHaveCount(1);
    expect($filtre->reponsesDues()->first()['contact']->id)->toBe($cPrana->id);
});
