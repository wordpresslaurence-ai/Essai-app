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
    /** Version du branchement : à augmenter quand la liste des événements reçus change. */
    public const VERSION_BRANCHEMENT = 2;

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
                str_contains($detail, 'not enough rights') || str_contains($detail, 'CHAT_ADMIN_REQUIRED') || str_contains($detail, 'chat not found')
                    => 'Le robot n’a pas les droits nécessaires dans ce canal : vérifie qu’il y est administrateur, avec « Inviter via un lien » et « Bannir des utilisateurs ».',
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
            'allowed_updates' => ['message', 'channel_post', 'my_chat_member', 'chat_member', 'callback_query'],
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

    /** Ajoute le bouton « ✓ Fait » sous une publication du canal. */
    public function ajouterBoutonFait(string $chatId, string $messageId, int $pratiqueId): void
    {
        $this->appeler('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => (int) $messageId,
            'reply_markup' => ['inline_keyboard' => [[['text' => '✓ Fait', 'callback_data' => 'fait:'.$pratiqueId]]]],
        ]);
    }

    /** Petite réponse affichée à la personne qui a cliqué sur un bouton. */
    public function repondreBouton(string $callbackId, string $texte): void
    {
        $this->appeler('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $texte]);
    }

    /** Lien d'invitation personnel : une seule entrée, expiration facultative (horodatage). */
    public function creerLienInvitation(string $chatId, string $nom, ?int $expire = null): string
    {
        $donnees = ['chat_id' => $chatId, 'name' => mb_substr($nom, 0, 32), 'member_limit' => 1];
        if ($expire) {
            $donnees['expire_date'] = $expire;
        }

        $resultat = $this->appeler('createChatInviteLink', $donnees);

        if (! is_array($resultat) || blank($resultat['invite_link'] ?? null)) {
            throw new RuntimeException('Telegram n’a pas renvoyé de lien d’invitation.');
        }

        return (string) $resultat['invite_link'];
    }

    public function revoquerLien(string $chatId, string $lien): void
    {
        $this->appeler('revokeChatInviteLink', ['chat_id' => $chatId, 'invite_link' => $lien]);
    }

    /** Retire quelqu'un du canal, sans l'empêcher d'y revenir plus tard avec un nouveau lien. */
    public function exclure(string $chatId, string $userId): void
    {
        $this->appeler('banChatMember', ['chat_id' => $chatId, 'user_id' => (int) $userId]);
        $this->appeler('unbanChatMember', ['chat_id' => $chatId, 'user_id' => (int) $userId, 'only_if_banned' => true]);
    }

    /** Envoie un message texte ; retourne son identifiant Telegram. */
    public function envoyer(string $chatId, string $texte): ?string
    {
        $resultat = $this->appeler('sendMessage', ['chat_id' => $chatId, 'text' => mb_substr($texte, 0, 4000)]);

        return is_array($resultat) && isset($resultat['message_id']) ? (string) $resultat['message_id'] : null;
    }
}
