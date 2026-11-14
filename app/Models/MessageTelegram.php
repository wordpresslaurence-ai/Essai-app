<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Message échangé avec un contact via le robot Telegram (reçu ou envoyé). */
class MessageTelegram extends Model
{
    public const ENTRANT = 'entrant';

    public const SORTANT = 'sortant';

    protected $table = 'messages_telegram';

    protected $fillable = ['contact_id', 'chat_id', 'sens', 'type', 'texte', 'expediteur', 'telegram_message_id', 'traite_le'];

    protected function casts(): array
    {
        return ['traite_le' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function estEntrant(): bool
    {
        return $this->sens === self::ENTRANT;
    }

    /** Texte à afficher : le message, ou une mention pour un vocal, une photo… */
    public function apercu(): string
    {
        $mention = match ($this->type) {
            'vocal' => '[message vocal : à écouter dans Telegram]',
            'photo' => '[photo : à voir dans Telegram]',
            'video' => '[vidéo : à voir dans Telegram]',
            'document' => '[fichier : à ouvrir dans Telegram]',
            'autre' => '[message non textuel : à voir dans Telegram]',
            default => '',
        };

        return trim($mention.' '.(string) $this->texte);
    }
}
