<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Publication d'un canal marquée #pratique : elle porte un bouton « ✓ Fait ». */
class PratiqueTelegram extends Model
{
    /** Mot-clé à mettre dans la publication (texte ou légende) pour ajouter le bouton. */
    public const MOT_CLE = '#pratique';

    protected $table = 'pratiques_telegram';

    protected $fillable = ['canal_telegram_id', 'message_id', 'titre', 'type', 'publie_le'];

    protected function casts(): array
    {
        return ['publie_le' => 'datetime'];
    }

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalTelegram::class, 'canal_telegram_id');
    }

    public function faites(): HasMany
    {
        return $this->hasMany(PratiqueFaite::class);
    }

    public static function estPratique(string $texte): bool
    {
        return mb_stripos($texte, self::MOT_CLE) !== false;
    }
}
