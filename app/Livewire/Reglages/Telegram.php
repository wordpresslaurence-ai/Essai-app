<?php

namespace App\Livewire\Reglages;

use App\Models\MessageTelegram;
use App\Models\Parametre;
use App\Services\Telegram\ClientTelegram;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Connexion du robot Telegram (brique 018) : on colle le jeton donné par
 * BotFather, le CRM vérifie le robot et lui indique où envoyer les messages.
 */
#[Layout('layouts.app')]
class Telegram extends Component
{
    /** Champ de saisie du jeton (vide au chargement : le jeton enregistré n'est jamais réaffiché). */
    public string $jeton = '';

    public ?string $erreur = null;

    /** @var array<string, mixed>|null Résultat de « Vérifier le branchement ». */
    public ?array $etat = null;

    public function connecter(): void
    {
        $this->reset('erreur', 'etat');

        $this->validate(
            ['jeton' => ['required', 'string', 'regex:/^\d+:[A-Za-z0-9_-]{20,}$/']],
            ['jeton.required' => 'Colle le jeton donné par BotFather.', 'jeton.regex' => 'Ce texte ne ressemble pas à un jeton de robot (il a la forme 123456789:AAH…).'],
        );

        try {
            $client = new ClientTelegram(trim($this->jeton));
            $moi = $client->moi();
            $secret = Str::random(48);
            $client->brancher(route('webhooks.telegram'), $secret);
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        Parametre::instance()->update([
            'telegram_jeton' => trim($this->jeton),
            'telegram_secret' => $secret,
            'telegram_robot' => (string) ($moi['username'] ?? ''),
        ]);

        $this->reset('jeton');
        session()->flash('message', 'Robot connecté : @'.($moi['username'] ?? '').'. Les messages qu’il reçoit arrivent maintenant dans le CRM.');
    }

    public function verifier(): void
    {
        $this->reset('erreur', 'etat');

        try {
            $this->etat = app(ClientTelegram::class)->etat();
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();
        }
    }

    /** Coupe le lien : le robot existe toujours dans Telegram, mais n'écrit plus au CRM. */
    public function deconnecter(): void
    {
        $this->reset('erreur', 'etat');

        try {
            app(ClientTelegram::class)->debrancher();
        } catch (Throwable) {
            // Jeton déjà révoqué côté Telegram : on efface quand même les réglages.
        }

        Parametre::instance()->update(['telegram_jeton' => null, 'telegram_secret' => null, 'telegram_robot' => null]);
        session()->flash('message', 'Robot déconnecté. Les comptes déjà reliés et les messages reçus sont conservés.');
    }

    public function supprimerMessage(int $id): void
    {
        MessageTelegram::whereNull('contact_id')->whereKey($id)->delete();
    }

    public function render(): View
    {
        $p = Parametre::instance();

        return view('livewire.reglages.telegram', [
            'robot' => $p->telegram_robot,
            'connecte' => filled($p->telegram_robot) && filled($p->secret('telegram_jeton')),
            'inconnus' => MessageTelegram::whereNull('contact_id')->where('sens', MessageTelegram::ENTRANT)->latest('id')->limit(30)->get(),
            'nbRelies' => \App\Models\Contact::whereNotNull('telegram_id')->count(),
        ]);
    }
}
