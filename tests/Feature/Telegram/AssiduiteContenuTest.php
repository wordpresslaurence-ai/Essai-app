<?php

use App\Livewire\Assistant\Brief;
use App\Livewire\Sessions\Acces;
use App\Livewire\Sessions\Contenus;
use App\Models\CanalTelegram;
use App\Models\Contact;
use App\Models\Contenu;
use App\Models\Parametre;
use App\Models\PratiqueFaite;
use App\Models\PratiqueTelegram;
use App\Models\Seance;
use App\Models\User;
use App\Services\Telegram\Assiduite;
use App\Services\Telegram\ClientTelegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Parametre::instance()->update([
        'telegram_jeton' => '123456789:AAHabcdefghijklmnopqrstuvwxyz012345',
        'telegram_secret' => 'secret-de-test',
        'telegram_robot' => 'laurence_prana_bot',
        'telegram_branchement' => ClientTelegram::VERSION_BRANCHEMENT,
    ]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);
});

function envoiTelegram(array $donnees)
{
    return test()->postJson(route('webhooks.telegram'), array_merge(['update_id' => 1], $donnees), ['X-Telegram-Bot-Api-Secret-Token' => 'secret-de-test']);
}

/** Trimestre en cours, relié au canal, avec une abonnée entrée dans le canal il y a trois semaines. */
function trimestreAvecAbonnee(): array
{
    $canal = CanalTelegram::create(['chat_id' => '-1001234', 'titre' => 'Prana Vidya']);
    $seance = Seance::create([
        'titre' => 'Trimestre hiver', 'canal_telegram_id' => $canal->id,
        'date_debut' => now()->subDays(25), 'fin_acces' => now()->addMonths(2)->toDateString(),
    ]);
    $inscription = $seance->inscrire(Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']));
    $inscription->update(['acces_lien' => 'https://t.me/+a', 'acces_telegram_id' => '777', 'acces_rejoint_le' => now()->subDays(21)]);

    return [$canal, $seance, $inscription];
}

it('ajoute le bouton « ✓ Fait » sous une publication #pratique et relie le contenu prévu ce jour-là', function () {
    [$canal, $seance] = trimestreAvecAbonnee();
    $prevu = $seance->contenus()->create(['date_prevue' => now()->toDateString(), 'format' => 'vocal', 'titre' => 'Respiration du matin']);

    envoiTelegram(['channel_post' => ['message_id' => 42, 'chat' => ['id' => -1001234, 'type' => 'channel', 'title' => 'Prana Vidya'],
        'voice' => ['duration' => 300], 'caption' => "Respiration du matin #pratique\nÀ faire au réveil"]])->assertOk();
    envoiTelegram(['channel_post' => ['message_id' => 43, 'chat' => ['id' => -1001234, 'type' => 'channel'], 'text' => 'Une annonce sans mot-clé']])->assertOk();

    $pratique = PratiqueTelegram::sole();
    expect($pratique->titre)->toBe('Respiration du matin');
    expect($pratique->type)->toBe('vocal');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/editMessageReplyMarkup')
        && $r['message_id'] === 42 && $r['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'fait:'.$pratique->id);
    Http::assertSentCount(1);

    expect($prevu->fresh()->statut)->toBe('publie');
    expect($prevu->fresh()->pratique_telegram_id)->toBe($pratique->id);
});

it('note le clic « ✓ Fait » une seule fois et répond à la personne', function () {
    [$canal, , $inscription] = trimestreAvecAbonnee();
    $pratique = PratiqueTelegram::create(['canal_telegram_id' => $canal->id, 'message_id' => '42', 'publie_le' => now()]);

    foreach ([1, 2] as $n) {
        envoiTelegram(['callback_query' => ['id' => 'cb'.$n, 'from' => ['id' => 777], 'data' => 'fait:'.$pratique->id]])->assertOk();
    }

    $fait = PratiqueFaite::sole();
    expect($fait->inscription_id)->toBe($inscription->id);
    expect($fait->contact_id)->toBe($inscription->contact_id);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/answerCallbackQuery') && $r['callback_query_id'] === 'cb1' && str_contains($r['text'], 'Bravo'));
    Http::assertSent(fn ($r) => str_contains($r->url(), '/answerCallbackQuery') && $r['callback_query_id'] === 'cb2' && str_contains($r['text'], 'déjà noté'));
});

it('compte les pratiques faites et signale qui décroche, une fois tous les 10 jours', function () {
    [$canal, $seance, $inscription] = trimestreAvecAbonnee();
    $ancienne = PratiqueTelegram::create(['canal_telegram_id' => $canal->id, 'message_id' => '1', 'publie_le' => now()->subDays(15)]);
    PratiqueTelegram::create(['canal_telegram_id' => $canal->id, 'message_id' => '2', 'publie_le' => now()->subDays(5)]);
    PratiqueTelegram::create(['canal_telegram_id' => $canal->id, 'message_id' => '3', 'publie_le' => now()->subDays(2)]);
    PratiqueFaite::create(['pratique_telegram_id' => $ancienne->id, 'telegram_user_id' => '777', 'fait_le' => now()->subDays(15)]);

    $bilan = Assiduite::bilan($inscription->fresh());
    expect($bilan['publiees'])->toBe(3);
    expect($bilan['faites'])->toBe(1);

    expect(Assiduite::decrocheurs()->pluck('inscription.id')->all())->toBe([$inscription->id]);
    Livewire::test(Acces::class, ['seance' => $seance])->assertSee('1/3 pratiques');

    Livewire::test(Brief::class)
        ->assertSee('Abonnés qui décrochent')
        ->assertSee('Marie Durand')
        ->call('vuAssiduite', $inscription->id)
        ->assertDontSee('Abonnés qui décrochent');
});

it('ne signale pas quelqu’un qui pratique encore', function () {
    [$canal, , $inscription] = trimestreAvecAbonnee();
    PratiqueTelegram::create(['canal_telegram_id' => $canal->id, 'message_id' => '2', 'publie_le' => now()->subDays(5)]);
    $recente = PratiqueTelegram::create(['canal_telegram_id' => $canal->id, 'message_id' => '3', 'publie_le' => now()->subDays(2)]);
    PratiqueFaite::create(['pratique_telegram_id' => $recente->id, 'telegram_user_id' => '777', 'fait_le' => now()->subDay()]);

    expect(Assiduite::decrocheurs())->toBeEmpty();
});

it('gère le calendrier : ajout, statut, copie d’un autre trimestre et rappel dans le Brief', function () {
    [, $seance] = trimestreAvecAbonnee();

    $page = Livewire::test(Contenus::class, ['seance' => $seance])
        ->set('date_prevue', now()->addDays(3)->toDateString())
        ->set('format', 'video')
        ->set('titre', '')
        ->call('ajouter')
        ->assertHasErrors(['titre'])
        ->set('titre', 'Les cinq vayus')
        ->call('ajouter')
        ->assertHasNoErrors()
        ->assertSet('date_prevue', now()->addDays(4)->toDateString())
        ->assertSee('Les cinq vayus');

    $contenu = Contenu::sole();
    expect($contenu->statut)->toBe('a_enregistrer');

    Livewire::test(Brief::class)->assertSee('Contenu à préparer')->assertSee('Les cinq vayus');

    $page->call('changer', $contenu->id, 'statut', 'pret')->call('changer', $contenu->id, 'statut', 'nimporte');
    expect($contenu->fresh()->statut)->toBe('pret');
    Livewire::test(Brief::class)->assertDontSee('Les cinq vayus');

    // Trimestre suivant : on reprend le plan, décalé de trois mois.
    $suivant = Seance::create(['titre' => 'Trimestre printemps', 'date_debut' => $seance->date_debut->copy()->addMonths(3)]);
    Livewire::test(Contenus::class, ['seance' => $suivant])
        ->set('source', (string) $seance->id)
        ->call('copierPlan')
        ->assertSee('1 publication(s) copiée(s)');

    $copie = $suivant->contenus()->sole();
    $decalage = (int) $seance->date_debut->copy()->startOfDay()->diffInDays($suivant->date_debut->copy()->startOfDay());
    expect($copie->date_prevue->toDateString())->toBe($contenu->date_prevue->copy()->addDays($decalage)->toDateString());
    expect($copie->statut)->toBe('pret');

    $this->get(route('sessions.contenu', $seance))->assertOk();
});

it('met à jour le branchement une seule fois à l’ouverture du CRM', function () {
    Parametre::instance()->update(['telegram_branchement' => 0]);

    Livewire::test(Brief::class);
    Livewire::test(Brief::class);

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/setWebhook') && in_array('callback_query', $r['allowed_updates'], true));
    expect(Parametre::instance()->fresh()->telegram_branchement)->toBe(ClientTelegram::VERSION_BRANCHEMENT);
});
