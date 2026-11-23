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

    /** Canal dont le panneau « message épinglé » est ouvert, et son texte. */
    public ?int $epingleCanalId = null;

    public string $texteEpingle = '';

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
            'telegram_branchement' => ClientTelegram::VERSION_BRANCHEMENT,
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

    /**
     * Met à jour le branchement (pour recevoir aussi ce qui se passe dans les canaux)
     * puis invite à publier un message dans chaque canal pour qu'il soit reconnu.
     */
    public function detecterCanaux(): void
    {
        $this->reset('erreur', 'etat');
        $p = Parametre::instance();

        try {
            $secret = $p->telegram_secret ?: Str::random(48);
            app(ClientTelegram::class)->brancher(route('webhooks.telegram'), $secret);
            $p->update(['telegram_secret' => $secret, 'telegram_branchement' => ClientTelegram::VERSION_BRANCHEMENT]);
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        session()->flash('message', 'C’est prêt. Publie maintenant un court message (par exemple « Bienvenue ») dans chacun de tes canaux, puis recharge cette page : ils apparaîtront dans la liste.');
    }

    /** Nouvel essai d'ajout du bouton « ✓ Fait » sous une pratique. */
    public function reessayerBouton(int $id): void
    {
        $this->reset('erreur', 'etat');
        $pratique = \App\Models\PratiqueTelegram::with('canal')->find($id);

        if ($pratique && ! $pratique->ajouterBouton(app(ClientTelegram::class))) {
            $this->erreur = $pratique->bouton_erreur;
        }
    }

    public function ouvrirEpingle(int $id): void
    {
        $canal = \App\Models\CanalTelegram::find($id);
        if (! $canal) {
            return;
        }

        $this->reset('erreur', 'etat');
        $this->epingleCanalId = $canal->id;
        $this->texteEpingle = $canal->message_epingle_texte
            ?: \App\Models\CanalTelegram::texteEpingleParDefaut((string) Parametre::instance()->telegram_robot);
    }

    public function fermerEpingle(): void
    {
        $this->reset('epingleCanalId', 'texteEpingle');
    }

    /**
     * Publie le message « Une question ? » dans le canal et l'épingle. S'il existe
     * déjà, son texte est simplement modifié (pas de doublon).
     */
    public function publierEpingle(): void
    {
        $this->reset('erreur');
        $canal = $this->epingleCanalId ? \App\Models\CanalTelegram::find($this->epingleCanalId) : null;
        if (! $canal) {
            return;
        }

        $this->validate(['texteEpingle' => ['required', 'string', 'max:3500']], ['texteEpingle.required' => 'Écris le message à épingler.']);
        $texte = trim($this->texteEpingle);
        $client = app(ClientTelegram::class);

        try {
            $modifie = false;
            if (filled($canal->message_epingle_id)) {
                try {
                    $client->modifierTexte($canal->chat_id, $canal->message_epingle_id, $texte);
                    $modifie = true;
                } catch (Throwable $e) {
                    // Texte identique : rien à changer. Message supprimé entre-temps : on en publie un nouveau.
                    $modifie = str_contains($e->getMessage(), 'not modified');
                }
            }

            if (! $modifie) {
                $id = $client->envoyer($canal->chat_id, $texte);
                $client->epingler($canal->chat_id, (string) $id);
                $canal->message_epingle_id = $id;
            }
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $canal->message_epingle_texte = $texte;
        $canal->save();

        $this->fermerEpingle();
        session()->flash('message', 'Message épinglé dans « '.$canal->nom().' ».');
    }

    public function oublierCanal(int $id): void
    {
        \App\Models\CanalTelegram::whereKey($id)->delete();
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
            'canaux' => \App\Models\CanalTelegram::orderBy('titre')->get(),
            'pratiques' => \App\Models\PratiqueTelegram::with('canal')->latest('id')->limit(8)->get(),
            'nbRelies' => \App\Models\Contact::whereNotNull('telegram_id')->count(),
        ]);
    }
}
