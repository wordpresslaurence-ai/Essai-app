<?php

namespace App\Models;

use Database\Factories\BoiteEmailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une boîte e-mail lue par l'assistant (brique 009). Plusieurs boîtes possibles,
 * chacune d'un fournisseur (Gmail / Hostinger / Autre) et rattachée à un pôle.
 * Le mot de passe est chiffré au repos (cast « encrypted », clé = APP_KEY).
 */
class BoiteEmail extends Model
{
    /** @use HasFactory<BoiteEmailFactory> */
    use HasFactory;

    protected $table = 'boites_email';

    public const FOURNISSEUR_GMAIL = 'gmail';

    public const FOURNISSEUR_HOSTINGER = 'hostinger';

    public const FOURNISSEUR_AUTRE = 'autre';

    protected $fillable = [
        'libelle',
        'fournisseur',
        'email',
        'mot_de_passe',
        'imap_host',
        'imap_port',
        'dossier_envoyes',
        'pole_id',
        'active',
        'derniere_synchro_at',
    ];

    protected $attributes = [
        'fournisseur' => self::FOURNISSEUR_GMAIL,
        'imap_port' => 993,
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'mot_de_passe' => 'encrypted',
            'imap_port' => 'integer',
            'active' => 'boolean',
            'derniere_synchro_at' => 'datetime',
        ];
    }

    /**
     * Réglages techniques pré-remplis par fournisseur :
     * [serveur IMAP, port, dossier « Envoyés », libellé lisible].
     *
     * @return array<string, array{host: string, port: int, envoyes: string, libelle: string}>
     */
    public static function presets(): array
    {
        return [
            self::FOURNISSEUR_GMAIL => [
                'host' => 'imap.gmail.com', 'port' => 993,
                'envoyes' => '[Gmail]/Sent Mail', 'libelle' => 'Gmail',
            ],
            self::FOURNISSEUR_HOSTINGER => [
                'host' => 'imap.hostinger.com', 'port' => 993,
                'envoyes' => 'INBOX.Sent', 'libelle' => 'Hostinger',
            ],
        ];
    }

    /** Libellés lisibles des fournisseurs (pour les menus déroulants). */
    public static function fournisseurs(): array
    {
        return [
            self::FOURNISSEUR_GMAIL => 'Gmail',
            self::FOURNISSEUR_HOSTINGER => 'Hostinger',
            self::FOURNISSEUR_AUTRE => 'Autre (IMAP)',
        ];
    }

    public function pole(): BelongsTo
    {
        return $this->belongsTo(Pole::class);
    }

    /** @param  Builder<BoiteEmail>  $query */
    public function scopeActives(Builder $query): void
    {
        $query->where('active', true);
    }

    /** Serveur IMAP effectif (valeur saisie, sinon preset du fournisseur). */
    public function hoteImap(): string
    {
        if (filled($this->imap_host)) {
            return $this->imap_host;
        }

        return self::presets()[$this->fournisseur]['host'] ?? '';
    }

    /** Port IMAP effectif. */
    public function portImap(): int
    {
        return $this->imap_port ?: 993;
    }

    /** Dossier « Envoyés » effectif (valeur saisie, sinon preset). */
    public function dossierEnvoyes(): string
    {
        if (filled($this->dossier_envoyes)) {
            return $this->dossier_envoyes;
        }

        return self::presets()[$this->fournisseur]['envoyes'] ?? 'INBOX.Sent';
    }

    /** Chaîne de connexion IMAP pour un dossier donné, ex. {imap.gmail.com:993/imap/ssl}INBOX. */
    public function chaineImap(string $dossier): string
    {
        return '{'.$this->hoteImap().':'.$this->portImap().'/imap/ssl}'.$dossier;
    }

    /** Dossiers lus : réception + envoyés. @return list<string> */
    public function dossiers(): array
    {
        return ['INBOX', $this->dossierEnvoyes()];
    }

    /** Nom d'affichage : le libellé, sinon l'adresse. */
    public function nomAffichage(): string
    {
        return filled($this->libelle) ? $this->libelle : (string) $this->email;
    }

    /**
     * Lit le mot de passe chiffré en tolérant une clé incompatible
     * (ex. APP_KEY régénérée) : renvoie null plutôt que de lever une exception.
     */
    public function secret(): ?string
    {
        try {
            return $this->mot_de_passe;
        } catch (\Throwable) {
            return null;
        }
    }
}
