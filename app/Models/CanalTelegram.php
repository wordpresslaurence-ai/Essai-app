<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Canal (ou groupe) Telegram où le robot est administrateur, détecté automatiquement. */
class CanalTelegram extends Model
{
    protected $table = 'canaux_telegram';

    protected $fillable = ['chat_id', 'titre', 'type', 'actif', 'message_epingle_id', 'message_epingle_texte'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    /** Texte proposé pour le message épinglé « Une question ? ». */
    public static function texteEpingleParDefaut(string $robot): string
    {
        return "Une question sur votre pratique ?\n\nÉcrivez-moi en privé ici : https://t.me/".$robot
            ."\nJe vous réponds personnellement.\n\nLaurence";
    }

    public function nom(): string
    {
        return $this->titre ?: 'Canal '.$this->chat_id;
    }
}
