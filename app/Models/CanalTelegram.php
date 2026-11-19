<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Canal (ou groupe) Telegram où le robot est administrateur, détecté automatiquement. */
class CanalTelegram extends Model
{
    protected $table = 'canaux_telegram';

    protected $fillable = ['chat_id', 'titre', 'type', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    public function nom(): string
    {
        return $this->titre ?: 'Canal '.$this->chat_id;
    }
}
