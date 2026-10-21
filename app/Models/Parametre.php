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
        'pitch_prospection',
        'derniere_synchro_at',
    ];

    /** Valeurs par défaut au niveau du modèle (reflétées dès la création). */
    protected $attributes = [
        'relance_delai_jours' => 5,
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

    /**
     * Lit un secret chiffré en tolérant une clé de chiffrement incompatible
     * (ex. APP_KEY régénérée) : renvoie null plutôt que de lever une exception.
     */
    public function secret(string $champ): ?string
    {
        try {
            return $this->{$champ};
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * L'assistant IA est-il configuré (clé API Claude présente) ?
     * La lecture des e-mails dépend, elle, de l'existence d'au moins une boîte
     * active (voir App\Models\BoiteEmail), gérée séparément depuis la brique 009.
     */
    public function estConfigure(): bool
    {
        return filled($this->secret('anthropic_api_key'));
    }
}
