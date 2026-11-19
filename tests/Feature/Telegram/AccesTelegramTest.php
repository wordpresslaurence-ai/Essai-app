<?php

use App\Livewire\Reglages\Telegram;
use App\Livewire\Sessions\Acces;
use App\Livewire\Sessions\Fiche;
use App\Livewire\Sessions\Formulaire;
use App\Models\Activite;
use App\Models\CanalTelegram;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Parametre;
use App\Models\Seance;
use App\Models\User;
use App\Services\Telegram\AccesTelegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Laurence Test']));
    Parametre::instance()->update([
        'telegram_jeton' => '123456789:AAHabcdefghijklmnopqrstuvwxyz012345',
        'telegram_secret' => 'secret-de-test',
        'telegram_robot' => 'laurence_prana_bot',
    ]);
});

function fauxTelegram(): void
{
    $n = 0;
    Http::fake(function ($requete) use (&$n) {
        $n++;

        return match (true) {
            str_contains($requete->url(), '/createChatInviteLink') => Http::response(['ok' => true, 'result' => ['invite_link' => 'https://t.me/+lien'.$n]]),
            str_contains($requete->url(), '/sendMessage') => Http::response(['ok' => true, 'result' => ['message_id' => 500 + $n]]),
            default => Http::response(['ok' => true, 'result' => true]),
        };
    });
}

/** Un trimestre en cours relié au canal « Prana Vidya », avec deux abonnés (une payée, un non). */
function trimestre(array $attributs = []): array
{
    $canal = CanalTelegram::create(['chat_id' => '-1001234', 'titre' => 'Prana Vidya', 'type' => 'channel']);
    $seance = Seance::create(array_merge([
        'titre' => 'Trimestre hiver', 'prix' => 120, 'canal_telegram_id' => $canal->id,
        'date_debut' => now()->subDays(5)->setTime(9, 0),
        'fin_acces' => now()->addMonths(3)->toDateString(),
        'limite_entree' => now()->addDays(9)->toDateString(),
    ], $attributs));

    $payee = $seance->inscrire(Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand', 'telephone' => '+32 475 11 22 33']));
    $payee->paiements()->create(['montant' => 120, 'paye_le' => now()->toDateString()]);
    $impaye = $seance->inscrire(Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Petit']));

    return [$canal, $seance, $payee, $impaye];
}

function telegramPost(array $donnees)
{
    return test()->postJson(route('webhooks.telegram'), array_merge(['update_id' => 1], $donnees), ['X-Telegram-Bot-Api-Secret-Token' => 'secret-de-test']);
}

// --- Détection des canaux ----------------------------------------------------

it('reconnaît un canal quand le robot y voit une publication, et note quand il en est retiré', function () {
    telegramPost(['channel_post' => ['message_id' => 1, 'chat' => ['id' => -1001234, 'type' => 'channel', 'title' => 'Prana Vidya'], 'text' => 'Bienvenue']])->assertOk();

    $canal = CanalTelegram::firstOrFail();
    expect($canal->chat_id)->toBe('-1001234');
    expect($canal->titre)->toBe('Prana Vidya');
    expect($canal->actif)->toBeTrue();

    telegramPost(['my_chat_member' => ['chat' => ['id' => -1001234, 'type' => 'channel', 'title' => 'Prana Vidya'], 'new_chat_member' => ['status' => 'left']]])->assertOk();
    expect($canal->fresh()->actif)->toBeFalse();
    expect(CanalTelegram::count())->toBe(1);
});

it('met à jour le branchement pour recevoir ce qui se passe dans les canaux', function () {
    fauxTelegram();

    Livewire::test(Telegram::class)->call('detecterCanaux')->assertSee('Publie maintenant un court message');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/setWebhook')
        && in_array('channel_post', $r['allowed_updates'], true)
        && in_array('chat_member', $r['allowed_updates'], true)
        && $r['secret_token'] === 'secret-de-test');
});

// --- Liens d'accès -----------------------------------------------------------

it('crée un lien personnel à usage unique pour les abonnés payés seulement', function () {
    fauxTelegram();
    [, $seance, $payee, $impaye] = trimestre();

    Livewire::test(Acces::class, ['seance' => $seance])
        ->assertSee('Prana Vidya')
        ->call('creerLiensPayes')
        ->assertSee('1 lien(s) d’accès créé(s)');

    expect($payee->fresh()->acces_lien)->toStartWith('https://t.me/+lien');
    expect($impaye->fresh()->acces_lien)->toBeNull();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/createChatInviteLink')
        && $r['chat_id'] === '-1001234' && $r['member_limit'] === 1 && $r['name'] === 'Marie Durand'
        && $r['expire_date'] === $seance->fin_acces->copy()->endOfDay()->timestamp);

    // Pas de second lien tant que le premier n'est pas retiré.
    Livewire::test(Acces::class, ['seance' => $seance])->call('creerLien', $payee->id);
    Http::assertSentCount(1);
});

it('prépare le message de bienvenue avec le lien, l’envoie par le robot et en garde la trace', function () {
    fauxTelegram();
    [, $seance, $payee] = trimestre();
    $payee->contact->forceFill(['telegram_id' => '555001'])->save();

    $page = Livewire::test(Acces::class, ['seance' => $seance])->call('preparerBienvenue', $payee->id);

    $lien = $payee->fresh()->acces_lien;
    expect($page->get('corps'))
        ->toContain('Bonjour Marie')
        ->toContain($lien)
        ->toContain($seance->fin_acces->translatedFormat('l j F Y'))
        ->toContain('https://t.me/laurence_prana_bot?start=');

    $page->call('envoyerParRobot')->assertSet('ouverteId', null);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage') && $r['chat_id'] === '555001' && str_contains($r['text'], $lien));
    expect($payee->fresh()->acces_envoye_le)->not->toBeNull();
    expect(Activite::where('titre', 'Accès Telegram envoyé : Trimestre hiver')->count())->toBe(1);
});

it('explique un canal où le robot n’a pas les droits', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: not enough rights to manage chat invite link'], 400)]);
    [, $seance, $payee] = trimestre();

    Livewire::test(Acces::class, ['seance' => $seance])
        ->call('creerLien', $payee->id)
        ->assertSee('Le robot n’a pas les droits nécessaires');

    expect($payee->fresh()->acces_lien)->toBeNull();
});

it('note l’entrée dans le canal grâce au lien personnel', function () {
    [, , $payee] = trimestre();
    $payee->update(['acces_lien' => 'https://t.me/+abc', 'acces_lien_cree_le' => now()]);

    telegramPost(['chat_member' => [
        'chat' => ['id' => -1001234, 'type' => 'channel'],
        'invite_link' => ['invite_link' => 'https://t.me/+abc'],
        'new_chat_member' => ['status' => 'member', 'user' => ['id' => 777888]],
    ]])->assertOk();

    $payee->refresh();
    expect($payee->acces_telegram_id)->toBe('777888');
    expect($payee->etatAcces())->toBe('rejoint');
});

// --- Fin de trimestre --------------------------------------------------------

it('retire l’accès à la fin du trimestre, sauf à ceux qui se sont réinscrits', function () {
    fauxTelegram();
    [$canal, $seance, $payee, $impaye] = trimestre(['fin_acces' => now()->subDay()->toDateString()]);
    $payee->update(['acces_lien' => 'https://t.me/+a', 'acces_telegram_id' => '111', 'acces_rejoint_le' => now()]);
    $impaye->update(['acces_lien' => 'https://t.me/+b', 'acces_telegram_id' => '222', 'acces_rejoint_le' => now()]);

    // Marie se réinscrit au trimestre suivant.
    $suivant = Seance::create(['titre' => 'Trimestre printemps', 'canal_telegram_id' => $canal->id, 'date_debut' => now()->addDays(2), 'fin_acces' => now()->addMonths(3)->toDateString()]);
    $suivant->inscrire($payee->contact);

    expect(app(AccesTelegram::class)->retirerExpires())->toBe(1);

    expect($impaye->fresh()->etatAcces())->toBe('retire');
    expect($payee->fresh()->acces_retire_le)->toBeNull();
    Http::assertSent(fn ($r) => str_contains($r->url(), '/banChatMember') && $r['user_id'] === 222);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/unbanChatMember') && $r['only_if_banned'] === true);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/banChatMember') && $r['user_id'] === 111);

    expect(app(AccesTelegram::class)->retirerExpires())->toBe(0);
});

it('crée une tâche de renouvellement deux semaines avant la fin et liste ceux qui ne sont pas réinscrits', function () {
    [$canal, $seance, $payee, $impaye] = trimestre(['fin_acces' => now()->addDays(10)->toDateString()]);
    $suivant = Seance::create(['titre' => 'Trimestre printemps', 'canal_telegram_id' => $canal->id, 'date_debut' => now()->addDays(11)->setTime(9, 0), 'fin_acces' => now()->addMonths(3)->toDateString()]);
    $suivant->inscrire($payee->contact);

    expect(app(AccesTelegram::class)->creerTachesRenouvellement())->toBe(1);
    expect(app(AccesTelegram::class)->creerTachesRenouvellement())->toBe(0);
    $tache = Activite::where('titre', 'like', 'Renouvellement :%')->firstOrFail();
    expect($tache->notes)->toContain('/sessions/'.$seance->id.'/acces');

    $page = Livewire::test(Acces::class, ['seance' => $seance])->call('ouvrirRenouvellement');
    $lignes = $page->viewData('lignesRenouvellement');
    expect($lignes)->toHaveCount(1);
    expect($lignes[0]['inscription']->id)->toBe($impaye->id);
    expect($lignes[0]['texte'])->toContain('Bonjour Paul')->toContain($suivant->date_debut->translatedFormat('l j F Y'));

    $page->call('marquerRenouvellement', $impaye->id);
    expect($tache->fresh()->estTerminee())->toBeTrue();
});

// --- Session ------------------------------------------------------------------

it('enregistre canal, fin de l’accès et date limite, ferme les inscriptions après la limite et garde le trimestre dans « À venir »', function () {
    $canal = CanalTelegram::create(['chat_id' => '-1001234', 'titre' => 'Prana Vidya']);

    Livewire::test(Formulaire::class)
        ->set('titre', 'Trimestre hiver')
        ->set('date_debut', now()->subDays(20)->format('Y-m-d\TH:i'))
        ->set('canal_telegram_id', (string) $canal->id)
        ->set('fin_acces', now()->addMonths(2)->toDateString())
        ->set('limite_entree', now()->subDay()->toDateString())
        ->call('enregistrer')
        ->assertHasNoErrors();

    $seance = Seance::where('titre', 'Trimestre hiver')->firstOrFail();
    expect($seance->canal_telegram_id)->toBe($canal->id);
    expect($seance->inscriptionsCloses())->toBeTrue();
    expect(Seance::aVenir()->whereKey($seance->id)->exists())->toBeTrue();
    expect(Seance::passees()->whereKey($seance->id)->exists())->toBeFalse();

    Livewire::test(Fiche::class, ['seance' => $seance])
        ->assertSee('La date limite d’entrée est passée')
        ->assertSee('Accès Telegram');

    $this->get(route('sessions.acces', $seance))->assertOk();
});
