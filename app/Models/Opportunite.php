<?php

namespace App\Models;

use Database\Factories\OpportuniteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $fillable = [
        'titre',
        'contact_id',
        'montant',
        'etape',
        'notes',
        'date_cloture',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_cloture' => 'date',
        ];
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

    // --- Relations -----------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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

    /** Change l'étape et gère la date de clôture (Gagné/Perdu). */
    public function changerEtape(string $etape): void
    {
        $donnees = ['etape' => $etape];
        $donnees['date_cloture'] = in_array($etape, [self::ETAPE_GAGNE, self::ETAPE_PERDU], true)
            ? now()->toDateString()
            : null;

        $this->update($donnees);
    }
}
