<?php

use App\Livewire\Assistant\Brief;
use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Reglages\Telegram;
use App\Models\Contact;
use App\Models\MessageTelegram;
use App\Models\ModeleMessage;
use App\Models\Parametre;
use App\Models\Seance;
use App\Models\User;
use App\Services\IA\AssistantFactice;
use App\Services\IA\AssistantIA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const JETON_ROBOT = '123456789:AAHabcdefghijklmnopqrstuvwxyz012345';

function robotConnecte(): void
{
    Parametre::instance()->update([
        'telegram_jeton' => JETON_ROBOT, 'telegram_secret' => 'secret-de-test', 'telegram_robot' => 'laurence_prana_bot',
    ]);
}

/** Simule un message envoyé au robot par Telegram. */
function messageRecu(array $message, string $secret = 'secret-de-test')
{
    return test()->postJson(route('webhooks.telegram'), ['update_id' => 1, 'message' => array_merge([
        'message_id' => 10,
        'chat' => ['id' => 555001, 'type' => 'private'],
        'from' => ['id' => 555001, 'first_name' => 'Marie', 'last_name' => 'Durand'],
    ], $message)], ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
}

// --- Réglages ----------------------------------------------------------------

it('connecte le robot : vérifie le jeton, branche le webhook et garde le jeton chiffré', function () {
    $this->actingAs(User::factory()->create());
    Http::fake([
        'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'laurence_prana_bot']]),
        'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
    ]);

    Livewire::test(Telegram::class)
        ->set('jeton', 'pas un jeton')
        ->call('connecter')
        ->assertHasErrors(['jeton'])
        ->set('jeton', JETON_ROBOT)
        ->call('connecter')
        ->assertHasNoErrors()
        ->assertSet('jeton', '')
        ->assertSee('@laurence_prana_bot');

    $p = Parametre::instance()->fresh();
    expect($p->telegram_robot)->toBe('laurence_prana_bot');
    expect($p->telegram_jeton)->toBe(JETON_ROBOT);
    expect($p->getRawOriginal('telegram_jeton'))->not->toContain('AAH');
    expect(strlen($p->telegram_secret))->toBe(48);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/setWebhook')
        && $r['url'] === route('webhooks.telegram') && $r['secret_token'] === $p->telegram_secret);
});

it('explique un jeton refusé sans l’enregistrer', function () {
    $this->actingAs(User::factory()->create());
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

    Livewire::test(Telegram::class)->set('jeton', JETON_ROBOT)->call('connecter')
        ->assertSee('Telegram refuse ce jeton');

    expect(Parametre::instance()->fresh()->telegram_robot)->toBeNull();
});

// --- Réception ---------------------------------------------------------------

it('refuse un appel sans le bon secret', function () {
    robotConnecte();

    messageRecu(['text' => 'Bonjour'], 'mauvais')->assertNotFound();
    expect(MessageTelegram::count())->toBe(0);
});

it('relie le compte Telegram à la fiche quand le lien personnel est ouvert, puis range les messages', function () {
    robotConnecte();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]])]);
    $contact = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']);

    $lien = $contact->lienTelegram();
    expect($lien)->toStartWith('https://t.me/laurence_prana_bot?start=');
    $jeton = $contact->fresh()->telegram_jeton_lien;

    messageRecu(['text' => '/start '.$jeton])->assertOk();

    $contact->refresh();
    expect($contact->telegram_id)->toBe('555001');
    expect($contact->telegram_nom)->toBe('Marie Durand');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r['text'], 'Bonjour Marie'));

    messageRecu(['message_id' => 11, 'text' => 'Puis-je pratiquer le soir ?'])->assertOk();
    messageRecu(['message_id' => 11, 'text' => 'Puis-je pratiquer le soir ?'])->assertOk(); // renvoi par Telegram
    messageRecu(['message_id' => 12, 'voice' => ['duration' => 12]])->assertOk();

    $messages = $contact->messagesTelegram()->get();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->texte)->toBe('Puis-je pratiquer le soir ?');
    expect($messages[1]->type)->toBe('vocal');
    expect($messages[1]->apercu())->toContain('message vocal');
});

it('garde à part le message d’un compte non reconnu et lui demande son lien, une seule fois', function () {
    robotConnecte();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]])]);

    messageRecu(['text' => 'Bonjour, qui êtes-vous ?'])->assertOk();
    messageRecu(['message_id' => 11, 'text' => 'Allô ?'])->assertOk();

    expect(MessageTelegram::whereNull('contact_id')->count())->toBe(2);
    Http::assertSentCount(1);

    // Le compte est ensuite relié : ses messages rejoignent la fiche.
    $contact = Contact::factory()->create();
    $contact->lienTelegram();
    messageRecu(['message_id' => 12, 'text' => '/start '.$contact->fresh()->telegram_jeton_lien])->assertOk();
    expect($contact->messagesTelegram()->count())->toBe(2);
});

it('ignore les messages de groupe', function () {
    robotConnecte();

    messageRecu(['chat' => ['id' => -100, 'type' => 'group'], 'text' => 'Bonjour à tous'])->assertOk();
    expect(MessageTelegram::count())->toBe(0);
});

// --- Fiche et Brief ----------------------------------------------------------

it('montre la question dans le Brief, propose une réponse et l’envoie depuis la fiche', function () {
    $this->actingAs(User::factory()->create());
    robotConnecte();
    app()->instance(AssistantIA::class, new AssistantFactice);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);

    $contact = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand', 'telegram_id' => null]);
    $contact->forceFill(['telegram_id' => '555001'])->save();
    MessageTelegram::create(['contact_id' => $contact->id, 'chat_id' => '555001', 'sens' => 'entrant', 'texte' => 'Puis-je pratiquer le soir ?', 'telegram_message_id' => '11']);

    Livewire::test(Brief::class)
        ->assertSee('Questions reçues sur Telegram')
        ->assertSee('Puis-je pratiquer le soir ?');

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('Compte relié')
        ->assertSee('Puis-je pratiquer le soir ?')
        ->call('envoyerTelegram')
        ->assertHasErrors(['telegramReponse'])
        ->call('proposerReponseTelegram')
        ->assertSet('telegramReponse', 'Bonjour Marie Durand, (réponse Telegram factice)')
        ->set('telegramReponse', 'Oui, le soir convient très bien.')
        ->call('envoyerTelegram')
        ->assertHasNoErrors()
        ->assertSet('telegramReponse', '');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage')
        && $r['chat_id'] === '555001' && $r['text'] === 'Oui, le soir convient très bien.');

    $sortant = MessageTelegram::where('sens', 'sortant')->firstOrFail();
    expect($sortant->texte)->toBe('Oui, le soir convient très bien.');
    expect(MessageTelegram::where('sens', 'entrant')->first()->traite_le)->not->toBeNull();

    Livewire::test(Brief::class)->assertDontSee('Questions reçues sur Telegram');
});

it('n’enregistre pas la réponse si Telegram la refuse', function () {
    $this->actingAs(User::factory()->create());
    robotConnecte();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);
    $contact = Contact::factory()->create();
    $contact->forceFill(['telegram_id' => '555001'])->save();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->set('telegramReponse', 'Bonjour')
        ->call('envoyerTelegram')
        ->assertSee('a bloqué le robot')
        ->assertSet('telegramReponse', 'Bonjour');

    expect(MessageTelegram::count())->toBe(0);
});

it('donne le lien personnel dans les messages, et rien tant que le robot n’est pas connecté', function () {
    $contact = Contact::factory()->create();
    $seance = Seance::create(['titre' => 'Stage']);

    expect($contact->lienTelegram())->toBeNull();
    expect(ModeleMessage::remplacer('Écrivez-moi : {lien_telegram}', ModeleMessage::valeurs($contact, $seance)))
        ->toBe('Écrivez-moi : [lien_telegram]');

    robotConnecte();
    expect(ModeleMessage::remplacer('{lien_telegram}', ModeleMessage::valeurs($contact->fresh(), $seance)))
        ->toStartWith('https://t.me/laurence_prana_bot?start=');
});
