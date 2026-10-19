<?php

use App\Models\Contact;
use App\Models\Email;
use App\Models\Parametre;
use App\Services\IA\AssistantClaude;
use App\Services\IA\AssistantFactice;
use App\Services\IA\ResultatAnalyse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('l’assistant factice renvoie un résultat exploitable', function () {
    $assistant = new AssistantFactice(suggestions: ['Relancer Marie', 'Proposer une date']);
    $contact = Contact::factory()->create(['nom' => 'Dubois', 'prenom' => 'Marie']);

    $r = $assistant->analyser($contact);
    expect($r)->toBeInstanceOf(ResultatAnalyse::class);
    expect($r->resume)->toContain('Dubois');

    expect($assistant->suggestions([]))->toBe(['Relancer Marie', 'Proposer une date']);
});

it('le client Claude construit la requête et parse la réponse (API simulée)', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'resume' => 'Le client a demandé un devis.',
                    'verdict' => 'Relancer : pas de réponse depuis 1 semaine.',
                    'delai' => 'sous 2 jours',
                    'brouillon' => 'Bonjour, avez-vous pu regarder ma proposition ?',
                ]),
            ]],
        ], 200),
    ]);

    $contact = Contact::factory()->create(['email' => 'client@example.com']);
    Email::create([
        'contact_id' => $contact->id,
        'direction' => Email::DIRECTION_ENTRANT,
        'de' => 'client@example.com', 'a' => 'laurence@gmail.com',
        'objet' => 'Devis', 'extrait' => 'Pouvez-vous me faire un devis ?',
        'date_email' => Carbon::parse('2026-05-01'),
        'message_id' => 'x1', 'boite' => 'laurence@gmail.com',
    ]);

    $r = (new AssistantClaude)->analyser($contact);

    expect($r->resume)->toBe('Le client a demandé un devis.');
    expect($r->verdict)->toContain('Relancer');
    expect($r->delaiConseille)->toBe('sous 2 jours');
    expect($r->brouillon)->toContain('proposition');

    // L'appel a bien visé l'API Claude avec la clé
    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'sk-ant-test')
        && str_contains($request->url(), 'api.anthropic.com'));
});

it('le client Claude signale une clé manquante', function () {
    $contact = Contact::factory()->create();

    expect(fn () => (new AssistantClaude)->analyser($contact))
        ->toThrow(RuntimeException::class);
});

it('le client Claude gère une erreur de l’API proprement', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'unauthorized'], 401)]);

    $contact = Contact::factory()->create();

    expect(fn () => (new AssistantClaude)->analyser($contact))
        ->toThrow(RuntimeException::class);
});

it('le client Claude parse une liste de suggestions', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['Relancer Marie', 'Confirmer la salle'])]],
        ], 200),
    ]);

    $suggestions = (new AssistantClaude)->suggestions(['réponses_dues' => 2]);

    expect($suggestions)->toBe(['Relancer Marie', 'Confirmer la salle']);
});
