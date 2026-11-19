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

    protected $fillable = ['canal_telegram_id', 'message_id', 'titre', 'type', 'publie_le', 'bouton_ajoute', 'bouton_erreur'];

    protected function casts(): array
    {
        return ['publie_le' => 'datetime', 'bouton_ajoute' => 'boolean'];
    }

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalTelegram::class, 'canal_telegram_id');
    }

    public function faites(): HasMany
    {
        return $this->hasMany(PratiqueFaite::class);
    }

    /** Ajoute le bouton sous la publication et garde le résultat (succès ou raison du refus). */
    public function ajouterBouton(\App\Services\Telegram\ClientTelegram $telegram): bool
    {
        try {
            $telegram->ajouterBoutonFait($this->canal->chat_id, $this->message_id, $this->id);
            $this->update(['bouton_ajoute' => true, 'bouton_erreur' => null]);

            return true;
        } catch (\Throwable $e) {
            $this->update(['bouton_ajoute' => false, 'bouton_erreur' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }

    public static function estPratique(string $texte): bool
    {
        return mb_stripos($texte, self::MOT_CLE) !== false;
    }
}
