<?php

namespace App\Services\Telegram;

use App\Models\Parametre;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Appels à l'API des robots Telegram (api.telegram.org). Le jeton du robot est
 * lu dans les réglages (chiffré) ; il n'apparaît jamais dans les messages d'erreur.
 */
class ClientTelegram
{
    public function __construct(private ?string $jeton = null) {}

    private function jeton(): string
    {
        $jeton = $this->jeton ?? Parametre::instance()->secret('telegram_jeton');

        if (blank($jeton)) {
            throw new RuntimeException('Le robot Telegram n’est pas encore connecté (Réglages → Telegram).');
        }

        return $jeton;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>|bool Le champ « result » de la réponse.
     */
    private function appeler(string $methode, array $donnees = []): array|bool
    {
        try {
            $reponse = Http::timeout(10)->connectTimeout(5)->asJson()
                ->post('https://api.telegram.org/bot'.$this->jeton().'/'.$methode, $donnees);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new RuntimeException('Telegram ne répond pas pour l’instant. Réessaie dans un moment.');
        }

        if (! $reponse->json('ok')) {
            $detail = (string) $reponse->json('description', 'erreur '.$reponse->status());

            throw new RuntimeException(match (true) {
                $reponse->status() === 401 || $reponse->status() === 404 => 'Telegram refuse ce jeton : vérifie que tu as copié le jeton complet donné par BotFather.',
                str_contains($detail, 'bot was blocked') => 'Cette personne a bloqué le robot : le message ne peut pas lui parvenir.',
                default => 'Telegram a refusé la demande : '.$detail,
            });
        }

        return $reponse->json('result');
    }

    /** @return array<string, mixed> Identité du robot (username, first_name…). */
    public function moi(): array
    {
        return (array) $this->appeler('getMe');
    }

    public function brancher(string $url, string $secret): void
    {
        $this->appeler('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ]);
    }

    public function debrancher(): void
    {
        $this->appeler('deleteWebhook');
    }

    /** @return array<string, mixed> État du branchement (adresse, dernière erreur, messages en attente). */
    public function etat(): array
    {
        return (array) $this->appeler('getWebhookInfo');
    }

    /** Envoie un message texte ; retourne son identifiant Telegram. */
    public function envoyer(string $chatId, string $texte): ?string
    {
        $resultat = $this->appeler('sendMessage', ['chat_id' => $chatId, 'text' => mb_substr($texte, 0, 4000)]);

        return is_array($resultat) && isset($resultat['message_id']) ? (string) $resultat['message_id'] : null;
    }
}
