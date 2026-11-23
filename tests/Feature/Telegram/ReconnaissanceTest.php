<?php

use App\Livewire\Reglages\Telegram;
use App\Models\CanalTelegram;
use App\Models\Contact;
use App\Models\MessageTelegram;
use App\Models\Parametre;
use App\Models\Seance;
use App\Models\User;
use App\Services\Telegram\ClientTelegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Parametre::instance()->update([
        'telegram_jeton' => '123456789:AAHabcdefghijklmnopqrstuvwxyz012345',
        'telegram_secret' => 'secret-de-test',
        'telegram_robot' => 'laurence_prana_bot',
        'telegram_branchement' => ClientTelegram::VERSION_BRANCHEMENT,
    ]);
});

function fauxRobot(): void
{
    $n = 0;
    Http::fake(function () use (&$n) {
        return Http::response(['ok' => true, 'result' => ['message_id' => 900 + (++$n)]]);
    });
}

function ecritAuRobot(array $message)
{
    return test()->postJson(route('webhooks.telegram'), ['update_id' => 1, 'message' => array_merge([
        'message_id' => 10,
        'chat' => ['id' => 777, 'type' => 'private'],
        'from' => ['id' => 777, 'first_name' => 'Marie'],
    ], $message)], ['X-Telegram-Bot-Api-Secret-Token' => 'secret-de-test']);
}

it('reconnaît un abonné entré dans le canal quand il écrit au robot par le lien général', function () {
    fauxRobot();
    $canal = CanalTelegram::create(['chat_id' => '-1001234', 'titre' => 'Prana Vidya']);
    $seance = Seance::create(['titre' => 'Trimestre', 'canal_telegram_id' => $canal->id]);
    $marie = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']);
    $seance->inscrire($marie)->update(['acces_lien' => 'https://t.me/+a', 'acces_telegram_id' => '777', 'acces_rejoint_le' => now()]);

    ecritAuRobot(['text' => '/start'])->assertOk();

    expect($marie->fresh()->telegram_id)->toBe('777');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r['text'], 'Bonjour Marie'));

    ecritAuRobot(['message_id' => 11, 'text' => 'Une question sur la pratique du soir'])->assertOk();
    expect($marie->messagesTelegram()->count())->toBe(1);
});

it('reconnaît aussi un abonné qui écrit directement, sans /start', function () {
    fauxRobot();
    $canal = CanalTelegram::create(['chat_id' => '-1001234']);
    $marie = Contact::factory()->create();
    Seance::create(['titre' => 'T', 'canal_telegram_id' => $canal->id])->inscrire($marie)->update(['acces_telegram_id' => '777']);

    ecritAuRobot(['text' => 'Bonjour !'])->assertOk();

    expect($marie->fresh()->telegram_id)->toBe('777');
    expect($marie->messagesTelegram()->count())->toBe(1);
    Http::assertNothingSent(); // pas de demande de numéro : elle est reconnue
});

it('propose de partager son numéro à un inconnu et le relie à sa fiche', function () {
    fauxRobot();
    $marie = Contact::factory()->create(['prenom' => 'Marie', 'telephone' => '0475 11 22 33', 'adresse_pays' => 'BE']);

    ecritAuRobot(['text' => 'Bonjour, une question'])->assertOk();
    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage')
        && ($r['reply_markup']['keyboard'][0][0]['request_contact'] ?? false) === true);

    ecritAuRobot(['message_id' => 11, 'contact' => ['phone_number' => '+32475112233', 'user_id' => 777]])->assertOk();

    $marie->refresh();
    expect($marie->telegram_id)->toBe('777');
    expect($marie->messagesTelegram()->pluck('texte')->all())->toContain('Bonjour, une question');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && str_contains($r['text'], 'Merci Marie')
        && ($r['reply_markup']['remove_keyboard'] ?? false) === true);
});

it('ne relie pas un numéro partagé qui n’est pas le sien, ni un numéro inconnu', function () {
    fauxRobot();
    $marie = Contact::factory()->create(['telephone' => '+32 475 11 22 33']);

    ecritAuRobot(['message_id' => 11, 'contact' => ['phone_number' => '+32475112233', 'user_id' => 999]])->assertOk();
    expect($marie->fresh()->telegram_id)->toBeNull();

    ecritAuRobot(['message_id' => 12, 'contact' => ['phone_number' => '+33600000000', 'user_id' => 777]])->assertOk();
    expect($marie->fresh()->telegram_id)->toBeNull();
    expect(MessageTelegram::whereNull('contact_id')->where('texte', 'Numéro partagé : +33600000000')->exists())->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r['text'] ?? '', 'Je ne retrouve pas ce numéro'));
});

it('publie et épingle le message « Une question ? », puis le met à jour sans doublon', function () {
    $this->actingAs(User::factory()->create());
    fauxRobot();
    $canal = CanalTelegram::create(['chat_id' => '-1001234', 'titre' => 'Prana Vidya']);

    $page = Livewire::test(Telegram::class)
        ->call('ouvrirEpingle', $canal->id)
        ->assertSet('texteEpingle', CanalTelegram::texteEpingleParDefaut('laurence_prana_bot'))
        ->call('publierEpingle')
        ->assertSee('Message épinglé dans « Prana Vidya »');

    $canal->refresh();
    expect($canal->message_epingle_id)->toBe('901');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && $r['chat_id'] === '-1001234' && str_contains($r['text'], 'https://t.me/laurence_prana_bot'));
    Http::assertSent(fn ($r) => str_contains($r->url(), '/pinChatMessage') && $r['message_id'] === 901);

    $page->call('ouvrirEpingle', $canal->id)->set('texteEpingle', 'Nouveau texte')->call('publierEpingle');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/editMessageText') && $r['message_id'] === 901 && $r['text'] === 'Nouveau texte');
    Http::assertSentCount(3);
    expect($canal->fresh()->message_epingle_texte)->toBe('Nouveau texte');
});
