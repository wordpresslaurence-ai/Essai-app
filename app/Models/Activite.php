<?php

namespace App\Models;

use Database\Factories\ActiviteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une activité : tâche, rappel ou rendez-vous, éventuellement liée à un contact
 * et/ou une opportunité.
 */
class Activite extends Model
{
    /** @use HasFactory<ActiviteFactory> */
    use HasFactory;

    public const TYPE_APPEL = 'appel';

    public const TYPE_RDV = 'rdv';

    public const TYPE_TACHE = 'tache';

    public const TYPE_EMAIL = 'email';

    public const TYPE_NOTE = 'note';

    protected $fillable = [
        'titre',
        'type',
        'contact_id',
        'opportunite_id',
        'echeance',
        'terminee_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'echeance' => 'datetime',
            'terminee_at' => 'datetime',
        ];
    }

    public static function libellesTypes(): array
    {
        return [
            self::TYPE_APPEL => 'Appel',
            self::TYPE_RDV => 'Rendez-vous',
            self::TYPE_TACHE => 'Tâche',
            self::TYPE_EMAIL => 'E-mail',
            self::TYPE_NOTE => 'Note',
        ];
    }

    public static function couleurType(string $type): string
    {
        return match ($type) {
            self::TYPE_APPEL => 'mint',
            self::TYPE_RDV => 'lav',
            self::TYPE_EMAIL => 'terra',
            default => 'gold',
        };
    }

    public function libelleType(): string
    {
        return self::libellesTypes()[$this->type] ?? $this->type;
    }

    // --- Relations -----------------------------------------------------------

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunite(): BelongsTo
    {
        return $this->belongsTo(Opportunite::class);
    }

    // --- Scopes --------------------------------------------------------------

    /** @param  Builder<Activite>  $query */
    public function scopeAFaire(Builder $query): void
    {
        $query->whereNull('terminee_at');
    }

    /** @param  Builder<Activite>  $query */
    public function scopeTerminees(Builder $query): void
    {
        $query->whereNotNull('terminee_at');
    }

    /** Activités à faire dont l'échéance est aujourd'hui ou passée. @param  Builder<Activite>  $query */
    public function scopeDuJourOuEnRetard(Builder $query): void
    {
        $query->whereNull('terminee_at')
            ->whereNotNull('echeance')
            ->where('echeance', '<=', now()->endOfDay());
    }

    // --- Aides ---------------------------------------------------------------

    public function estTerminee(): bool
    {
        return $this->terminee_at !== null;
    }

    public function enRetard(): bool
    {
        return ! $this->estTerminee()
            && $this->echeance !== null
            && $this->echeance->isPast()
            && ! $this->echeance->isToday();
    }

    /** Coche / décoche l'activité comme terminée. */
    public function basculer(): void
    {
        $this->update(['terminee_at' => $this->estTerminee() ? null : now()]);
    }
}
