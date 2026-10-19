<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages de l'assistant (ligne unique). Les secrets sont chiffrés au repos
 * grâce aux casts « encrypted » (clé = APP_KEY).
 */
class Parametre extends Model
{
    protected $table = 'parametres';

    protected $fillable = [
        'gmail_email',
        'gmail_app_password',
        'anthropic_api_key',
        'anthropic_modele',
        'relance_delai_jours',
        'lien_appel_visio',
        'lien_seance_decouverte',
        'lien_journee_aura',
        'derniere_synchro_at',
    ];

    protected function casts(): array
    {
        return [
            'gmail_app_password' => 'encrypted',
            'anthropic_api_key' => 'encrypted',
            'relance_delai_jours' => 'integer',
            'derniere_synchro_at' => 'datetime',
        ];
    }

    /**
     * Retourne l'unique ligne de réglages, en la créant au besoin.
     */
    public static function instance(): self
    {
        return static::firstOrCreate([]);
    }

    /** L'assistant est-il configuré (boîte + clé API) ? */
    public function estConfigure(): bool
    {
        return filled($this->gmail_email)
            && filled($this->gmail_app_password)
            && filled($this->anthropic_api_key);
    }
}
