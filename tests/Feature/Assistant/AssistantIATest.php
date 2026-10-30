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

it('le client Claude parse les champs enrichis et normalise les valeurs invalides', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'resume' => 'r', 'verdict' => 'v', 'delai' => null, 'brouillon' => 'b',
                'intention' => 'demande_devis',
                'temperature_conseillee' => 'chaud',
                'etape_conseillee' => 'pas_une_etape', // invalide -> null
                'infos_cles' => ['budget' => '2000 €', 'dates_rdv' => ['2026-08-01']],
                'promesses' => [['texte' => 'Rappeler jeudi', 'echeance' => '2026-07-03'], ['texte' => '']],
                'rdv' => ['demande' => true, 'date' => '2026-08-01', 'details' => 'visio'],
            ])]],
        ], 200),
    ]);

    $r = (new AssistantClaude)->analyser(Contact::factory()->create());

    expect($r->intention)->toBe('demande_devis');
    expect($r->temperatureConseillee)->toBe('chaud');
    expect($r->etapeConseillee)->toBeNull();            // valeur hors liste -> normalisée à null
    expect($r->infosCles['budget'])->toBe('2000 €');
    expect($r->promesses)->toHaveCount(1);              // promesse au texte vide ignorée
    expect($r->rdv['date'])->toBe('2026-08-01');
});

it('inclut l’offre de la catégorie du contact dans la requête d’analyse', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);

    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $cat = App\Models\Categorie::create([
        'pole_id' => $pole->id, 'nom' => 'Formation profs',
        'offre' => 'Dates : mars 2027. Tarif : 1500 euros.',
        'liens' => [['libelle' => 'Programme', 'url' => 'https://exemple.be/formation-prana']],
    ]);
    $contact = Contact::factory()->create(['categorie_id' => $cat->id]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['resume' => 'r', 'verdict' => 'v', 'brouillon' => 'b'])]],
        ], 200),
    ]);

    (new AssistantClaude)->analyser($contact);

    Http::assertSent(function ($request) {
        $data = json_encode($request->data());

        return str_contains($data, 'Offre de r') // « Offre de référence »
            && str_contains($data, '1500')
            && str_contains($data, 'formation-prana');
    });
});

it('récupère une analyse lisible même si la réponse JSON est coupée', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);

    // Réponse réaliste : entourée d'une clôture ```json et COUPÉE en plein
    // brouillon (comme quand le modèle dépasse la limite de tokens). Les sauts
    // de ligne sont des « \n » littéraux, comme dans du vrai JSON.
    $tronque = "```json\n".'{ "resume": "Le contact demande le programme.", '
        .'"verdict": "Oui, répondre vite.", "delai": "sous 24 h", '
        .'"brouillon": "Bonjour,\n\nVoici le programme en bref :\n- Stage 3 (lieu [à compléter]) : pratique pranique complète sur soi';

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $tronque]],
        ], 200),
    ]);

    $r = (new AssistantClaude)->analyser(Contact::factory()->create());

    // Les champs complets sont bien lus…
    expect($r->resume)->toBe('Le contact demande le programme.');
    expect($r->verdict)->toContain('répondre vite');
    expect($r->delaiConseille)->toBe('sous 24 h');

    // …et le brouillon est récupéré avec de VRAIS retours à la ligne,
    // sans « \n » littéral ni accolades JSON brutes.
    expect($r->brouillon)->toContain('Bonjour,')
        ->toContain("\n")               // vrai saut de ligne
        ->not->toContain('\n')          // pas de « \n » littéral
        ->not->toContain('"resume"');   // pas de JSON brut déversé
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

it('l’assistant factice rédige un mail de prospection', function () {
    $assistant = new AssistantFactice();
    $contact = Contact::factory()->create(['nom' => 'Martin', 'prenom' => 'Claire']);

    $r = $assistant->redigerProspection($contact, 'enseigne le yin');
    expect($r)->toBeInstanceOf(App\Services\IA\ResultatProspection::class);
    expect($r->corps)->toContain('Claire');
});

it('le client Claude rédige une prospection à partir du pitch (API simulée)', function () {
    Parametre::instance()->update([
        'anthropic_api_key' => 'sk-ant-test',
        'pitch_prospection' => 'Je forme des profs de yoga Prana Vidya.',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'objet' => 'Rejoindre la formation Prana Vidya',
                    'corps' => 'Bonjour, je serais ravie de vous présenter ma formation. Laurence',
                ]),
            ]],
        ], 200),
    ]);

    $contact = Contact::factory()->create(['nom' => 'Martin', 'prenom' => 'Claire']);
    $r = (new AssistantClaude())->redigerProspection($contact);

    expect($r->objet)->toContain('Prana Vidya');
    expect($r->corps)->toContain('Laurence');
});

it('refuse de rédiger une prospection sans offre de pôle ni note générale', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test', 'pitch_prospection' => null]);
    $contact = Contact::factory()->create(); // sans catégorie -> sans pôle

    expect(fn () => (new App\Services\IA\AssistantClaude())->redigerProspection($contact))
        ->toThrow(RuntimeException::class);
});

it('rédige une prospection à partir de l’offre de la catégorie, sans note générale', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test', 'pitch_prospection' => null]);

    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $cat = App\Models\Categorie::create([
        'pole_id' => $pole->id, 'nom' => 'Formation profs',
        'offre' => 'Formation de profs de yoga sur 3 stages.',
    ]);
    $contact = Contact::factory()->create(['categorie_id' => $cat->id]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['objet' => 'O', 'corps' => 'C Laurence'])]],
        ], 200),
    ]);

    $r = (new AssistantClaude())->redigerProspection($contact);
    expect($r->corps)->toContain('Laurence');
});

it('insère les liens de la catégorie du contact dans la requête de prospection', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test', 'pitch_prospection' => null]);

    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $cat = App\Models\Categorie::create([
        'pole_id' => $pole->id, 'nom' => 'Formation profs',
        'offre' => 'Formation de profs de yoga Prana Vidya.',
        'liens' => [['libelle' => 'Programme', 'url' => 'https://exemple.be/formation-prana']],
    ]);
    $contact = Contact::factory()->create(['categorie_id' => $cat->id]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['objet' => 'O', 'corps' => 'C Laurence'])]],
        ], 200),
    ]);

    (new AssistantClaude())->redigerProspection($contact);

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'formation-prana'));
});

it('prospecte sur l’offre de la catégorie courante (pas une autre) et sans forcer « la formation »', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test', 'pitch_prospection' => null]);

    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $cat = App\Models\Categorie::create([
        'pole_id' => $pole->id, 'nom' => 'Journée aura et chakra',
        'offre' => 'Journée de nettoyage de l’aura et équilibrage des chakras.',
    ]);
    $contact = Contact::factory()->create(['categorie_id' => $cat->id]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['objet' => 'O', 'corps' => 'C Laurence'])]],
        ], 200),
    ]);

    (new AssistantClaude())->redigerProspection($contact);

    Http::assertSent(function ($request) {
        // JSON_UNESCAPED_UNICODE : sinon les accents sont échappés (é) et ne matchent pas.
        $charge = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        // L'offre de la catégorie courante est bien transmise…
        expect($charge)->toContain('Journée aura et chakra');
        expect($charge)->toContain('aura');

        // …et le prompt système n'impose plus « rejoindre la formation ».
        expect($charge)->not->toContain('rejoindre la formation');

        return true;
    });
});

it('l’assistant factice rédige une réponse', function () {
    $r = (new AssistantFactice())->redigerReponse(Contact::factory()->create(['nom' => 'Durand']));

    expect($r)->toBeInstanceOf(App\Services\IA\ResultatProspection::class);
    expect($r->corps)->toContain('Durand');
});

it('le client Claude rédige une réponse au dernier mail reçu (API simulée)', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);
    $contact = Contact::factory()->create(['nom' => 'Durand']);
    Email::create([
        'contact_id' => $contact->id, 'direction' => Email::DIRECTION_ENTRANT,
        'de' => 'd@x.be', 'a' => 'moi@x.be', 'objet' => 'Question tarif', 'extrait' => 'Combien coûte…',
        'date_email' => now(), 'message_id' => uniqid(), 'boite' => 'moi@x.be',
    ]);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['objet' => 'Re: Question tarif', 'corps' => 'Bonjour, voici… Laurence'])]],
    ], 200)]);

    $r = (new AssistantClaude())->redigerReponse($contact);

    expect($r->objet)->toContain('Re:');
    expect($r->corps)->toContain('Laurence');
    Http::assertSent(fn ($request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'Question tarif'));
});

it('refuse de rédiger une réponse sans aucun mail reçu', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);
    $contact = Contact::factory()->create();

    expect(fn () => (new AssistantClaude())->redigerReponse($contact))->toThrow(RuntimeException::class);
});

it('propose un brouillon de réponse depuis la fiche contact', function () {
    $this->actingAs(App\Models\User::factory()->create());
    app()->instance(App\Services\IA\AssistantIA::class, new AssistantFactice(
        reponse: new App\Services\IA\ResultatProspection('Re: votre message', 'Bonjour Durand, voici… Laurence', 'factice'),
    ));
    $contact = Contact::factory()->create(['nom' => 'Durand']);

    Livewire\Livewire::test(App\Livewire\Contacts\FicheDetail::class, ['contact' => $contact])
        ->call('redigerReponse')
        ->assertSet('reponseObjet', 'Re: votre message')
        ->assertSet('reponseCorps', 'Bonjour Durand, voici… Laurence');
});

it('le client Claude devine un prospect depuis la signature (API simulée)', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode([
            'prenom' => 'Marie', 'nom' => 'Durand', 'entreprise' => 'Studio Zen', 'fonction' => 'Professeure', 'telephone' => null,
        ])]],
    ], 200)]);

    $infos = (new AssistantClaude())->devinerProspect('marie@zen.be', 'Bonjour, Marie Durand, Studio Zen');

    expect($infos['prenom'])->toBe('Marie');
    expect($infos['entreprise'])->toBe('Studio Zen');
    expect($infos['telephone'])->toBeNull();
});

it('rédige la prospection sur l’offre choisie, pas celle du contact', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test', 'pitch_prospection' => null]);

    $pole = App\Models\Pole::create(['nom' => 'Prana']);
    $catContact = App\Models\Categorie::create(['pole_id' => $pole->id, 'nom' => 'Recherche de salle', 'offre' => 'Je cherche une salle à louer.']);
    $catOffre = App\Models\Categorie::create(['pole_id' => $pole->id, 'nom' => 'Journée nettoyage chakra', 'offre' => 'Journée de nettoyage des chakras.']);
    $contact = Contact::factory()->create(['categorie_id' => $catContact->id]);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['objet' => 'O', 'corps' => 'C Laurence'])]],
    ], 200)]);

    (new AssistantClaude())->redigerProspection($contact, null, $catOffre->id);

    Http::assertSent(function ($request) {
        $charge = json_encode($request->data(), JSON_UNESCAPED_UNICODE);
        expect($charge)->toContain('nettoyage des chakras');      // l'offre choisie
        expect($charge)->not->toContain('Je cherche une salle');  // pas celle du contact

        return true;
    });
});
