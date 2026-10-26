<?php

namespace App\Models;

use Database\Factories\OpportuniteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une opportunité commerciale (deal) positionnée sur une étape du pipeline.
 */
class Opportunite extends Model
{
    /** @use HasFactory<OpportuniteFactory> */
    use HasFactory;

    public const ETAPE_NOUVEAU = 'nouveau';

    public const ETAPE_QUALIFIE = 'qualifie';

    public const ETAPE_PROPOSITION = 'proposition';

    public const ETAPE_GAGNE = 'gagne';

    public const ETAPE_PERDU = 'perdu';

    /** Jours sans changement d'étape au-delà desquels une opportunité « stagne ». */
    public const JOURS_STAGNATION = 21;

    /** Rôle du contact pour cette opportunité. */
    public const ROLES = [
        'participant' => 'Participant',
        'partenaire' => 'Partenaire',
        'les_deux' => 'Les deux',
    ];

    protected $fillable = [
        'titre',
        'contact_id',
        'categorie_id',
        'montant',
        'date_creation',
        'role',
        'etape',
        'etape_changee_le',
        'notes',
        'date_cloture',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_creation' => 'date',
            'date_cloture' => 'date',
            'etape_changee_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // À la création, on initialise la date de dernier changement d'étape
        // et, à défaut, la date de création au jour même.
        static::creating(function (self $opp) {
            if ($opp->etape_changee_le === null) {
                $opp->etape_changee_le = now();
            }
            if ($opp->date_creation === null) {
                $opp->date_creation = now()->toDateString();
            }
        });
    }

    /** Libellés lisibles des étapes. */
    public static function libellesEtapes(): array
    {
        return [
            self::ETAPE_NOUVEAU => 'Nouveau',
            self::ETAPE_QUALIFIE => 'Qualifié',
            self::ETAPE_PROPOSITION => 'Proposition',
            self::ETAPE_GAGNE => 'Gagné',
            self::ETAPE_PERDU => 'Perdu',
        ];
    }

    /** Étapes affichées dans le tableau kanban (hors Perdu). */
    public static function etapesActives(): array
    {
        return [self::ETAPE_NOUVEAU, self::ETAPE_QUALIFIE, self::ETAPE_PROPOSITION, self::ETAPE_GAGNE];
    }

    /** Couleur d'accent par étape (jetons du design system). */
    public static function couleurEtape(string $etape): string
    {
        return match ($etape) {
            self::ETAPE_NOUVEAU => 'deco',
            self::ETAPE_QUALIFIE => 'lav',
            self::ETAPE_PROPOSITION => 'gold',
            self::ETAPE_GAGNE => 'mint',
            self::ETAPE_PERDU => 'terra',
            default => 'lav',
        };
    }

    public function libelleEtape(): string
    {
        return self::libellesEtapes()[$this->etape] ?? $this->etape;
    }

    /** Libellé lisible du rôle (ou null si non renseigné). */
    public function libelleRole(): ?string
    {
        return $this->role ? (self::ROLES[$this->role] ?? $this->role) : null;
    }

    /** Couleur d'accent par rôle (jetons du design system). */
    public static function couleurRole(?string $role): string
    {
        return match ($role) {
            'participant' => 'lav',
            'partenaire' => 'gold',
            'les_deux' => 'mint',
            default => 'muted',
        };
    }

    // --- Relations -----------------------------------------------------------

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class);
    }

    // --- Scopes --------------------------------------------------------------

    /** @param  Builder<Opportunite>  $query */
    public function scopeEnCours(Builder $query): void
    {
        $query->whereNotIn('etape', [self::ETAPE_GAGNE, self::ETAPE_PERDU]);
    }

    /** @param  Builder<Opportunite>  $query */
    public function scopeGagnees(Builder $query): void
    {
        $query->where('etape', self::ETAPE_GAGNE);
    }

    /** @param  Builder<Opportunite>  $query */
    public function scopePerdues(Builder $query): void
    {
        $query->where('etape', self::ETAPE_PERDU);
    }

    /** @param  Builder<Opportunite>  $query */
    public function scopeEtape(Builder $query, string $etape): void
    {
        $query->where('etape', $etape);
    }

    /**
     * Opportunités « qui stagnent » : ouvertes et sans changement d'étape depuis
     * N jours (défaut : JOURS_STAGNATION). On se rabat sur updated_at si besoin.
     *
     * @param  Builder<Opportunite>  $query
     */
    public function scopeStagnantes(Builder $query, ?int $jours = null): void
    {
        $limite = now()->subDays($jours ?? self::JOURS_STAGNATION)->toDateTimeString();

        $query->whereNotIn('etape', [self::ETAPE_GAGNE, self::ETAPE_PERDU])
            ->whereRaw('coalesce(etape_changee_le, updated_at) < ?', [$limite]);
    }

    /** Nombre de jours depuis le dernier changement d'étape. */
    public function joursDepuisChangement(): int
    {
        $ref = $this->etape_changee_le ?? $this->updated_at;

        return $ref ? (int) $ref->diffInDays(now()) : 0;
    }

    // --- Aides ---------------------------------------------------------------

    public function estGagnee(): bool
    {
        return $this->etape === self::ETAPE_GAGNE;
    }

    public function estPerdue(): bool
    {
        return $this->etape === self::ETAPE_PERDU;
    }

    public function estTerminee(): bool
    {
        return $this->estGagnee() || $this->estPerdue();
    }

    /** Change l'étape, enregistre la date de changement et gère la clôture (Gagné/Perdu). */
    public function changerEtape(string $etape): void
    {
        $donnees = ['etape' => $etape, 'etape_changee_le' => now()];
        $donnees['date_cloture'] = in_array($etape, [self::ETAPE_GAGNE, self::ETAPE_PERDU], true)
            ? now()->toDateString()
            : null;

        $this->update($donnees);
    }
}
