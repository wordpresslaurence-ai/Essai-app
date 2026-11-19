<?php

namespace App\Http\Controllers;

use App\Models\CanalTelegram;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\MessageTelegram;
use App\Models\Parametre;
use App\Services\Telegram\ClientTelegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Reçoit les messages envoyés au robot Telegram. Appel de serveur à serveur :
 * Telegram joint à chaque appel le secret convenu au branchement.
 */
class WebhookTelegramController extends Controller
{
    public function __invoke(Request $request, ClientTelegram $telegram): JsonResponse
    {
        $attendu = (string) Parametre::instance()->telegram_secret;
        $recu = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        abort_unless($attendu !== '' && hash_equals($attendu, $recu), 404);

        // Le robot voit une publication dans un canal où il est administrateur : on mémorise le canal.
        if (is_array($post = $request->input('channel_post'))) {
            $this->memoriserCanal((array) data_get($post, 'chat'), true);

            return response()->json(['ok' => true]);
        }

        // Le robot est ajouté à un canal (ou en est retiré).
        if (is_array($changement = $request->input('my_chat_member'))) {
            $statut = (string) data_get($changement, 'new_chat_member.status');
            $this->memoriserCanal((array) data_get($changement, 'chat'), $statut === 'administrator');

            return response()->json(['ok' => true]);
        }

        // Quelqu'un rejoint un canal avec son lien personnel : on note son compte sur l'inscription.
        if (is_array($membre = $request->input('chat_member'))) {
            $lien = (string) data_get($membre, 'invite_link.invite_link');
            $statut = (string) data_get($membre, 'new_chat_member.status');

            if ($lien !== '' && in_array($statut, ['member', 'restricted'], true)) {
                $inscription = Inscription::where('acces_lien', $lien)->first();
                $inscription?->update([
                    'acces_telegram_id' => (string) data_get($membre, 'new_chat_member.user.id'),
                    'acces_rejoint_le' => $inscription->acces_rejoint_le ?? now(),
                ]);
            }

            return response()->json(['ok' => true]);
        }

        $message = $request->input('message');

        // Seules les conversations privées avec le robot nous intéressent.
        if (! is_array($message) || data_get($message, 'chat.type') !== 'private') {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) data_get($message, 'chat.id');
        $texte = (string) (data_get($message, 'text') ?? data_get($message, 'caption') ?? '');
        $expediteur = trim(data_get($message, 'from.first_name', '').' '.data_get($message, 'from.last_name', ''))
            ?: (string) data_get($message, 'from.username', '');

        // « /start <jeton> » : la personne ouvre son lien personnel, on relie son compte à sa fiche.
        if (preg_match('/^\/start(?:\s+(\S+))?/', $texte, $m)) {
            $contact = isset($m[1]) ? Contact::where('telegram_jeton_lien', $m[1])->first() : null;

            if ($contact) {
                Contact::where('telegram_id', $chatId)->whereKeyNot($contact->id)
                    ->update(['telegram_id' => null, 'telegram_nom' => null]);
                $contact->forceFill(['telegram_id' => $chatId, 'telegram_nom' => $expediteur ?: null])->save();
                MessageTelegram::where('chat_id', $chatId)->whereNull('contact_id')->update(['contact_id' => $contact->id]);

                $this->repondre($telegram, $chatId, 'Bonjour '.($contact->prenom ?: $contact->nomComplet())
                    .', c’est bien noté. Vous pouvez m’écrire ici vos questions, je vous répondrai personnellement. Laurence');
            } elseif (! Contact::where('telegram_id', $chatId)->exists()) {
                $this->repondre($telegram, $chatId, 'Bonjour. Pour que je vous reconnaisse, ouvrez le lien personnel que je vous ai envoyé. Laurence');
            }

            return response()->json(['ok' => true]);
        }

        $contact = Contact::where('telegram_id', $chatId)->first();

        $type = match (true) {
            data_get($message, 'text') !== null => 'texte',
            data_get($message, 'voice') !== null, data_get($message, 'audio') !== null => 'vocal',
            data_get($message, 'photo') !== null => 'photo',
            data_get($message, 'video') !== null, data_get($message, 'video_note') !== null => 'video',
            data_get($message, 'document') !== null => 'document',
            default => 'autre',
        };

        // firstOrCreate : Telegram renvoie un message tant qu'il n'a pas reçu de réponse 200.
        $ligne = MessageTelegram::firstOrCreate(
            ['chat_id' => $chatId, 'sens' => MessageTelegram::ENTRANT, 'telegram_message_id' => (string) data_get($message, 'message_id')],
            ['contact_id' => $contact?->id, 'type' => $type, 'texte' => $texte !== '' ? $texte : null, 'expediteur' => $expediteur ?: null],
        );

        if (! $contact && $ligne->wasRecentlyCreated
            && MessageTelegram::where('chat_id', $chatId)->where('sens', MessageTelegram::ENTRANT)->count() === 1) {
            $this->repondre($telegram, $chatId, 'Bonjour, j’ai bien reçu votre message. Pour que je vous reconnaisse, ouvrez le lien personnel que je vous ai envoyé. Laurence');
        }

        return response()->json(['ok' => true]);
    }

    /** @param  array<string, mixed>  $chat */
    private function memoriserCanal(array $chat, bool $actif): void
    {
        if (! in_array($chat['type'] ?? null, ['channel', 'supergroup', 'group'], true) || ! isset($chat['id'])) {
            return;
        }

        CanalTelegram::updateOrCreate(
            ['chat_id' => (string) $chat['id']],
            ['titre' => isset($chat['title']) ? mb_substr((string) $chat['title'], 0, 255) : null, 'type' => (string) $chat['type'], 'actif' => $actif],
        );
    }

    /** Réponse automatique : un échec d'envoi ne doit pas faire échouer la réception. */
    private function repondre(ClientTelegram $telegram, string $chatId, string $texte): void
    {
        try {
            $telegram->envoyer($chatId, $texte);
        } catch (Throwable) {
            // Sans gravité : le message reçu est enregistré de toute façon.
        }
    }
}
