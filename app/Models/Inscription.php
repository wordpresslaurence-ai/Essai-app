<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription d'un contact à une séance (confirmé / liste d'attente / annulé).
 */
class Inscription extends Model
{
    public const STATUT_CONFIRME = 'confirme';

    public const STATUT_ATTENTE = 'attente';

    public const STATUT_ANNULE = 'annule';

    protected $fillable = ['seance_id', 'contact_id', 'statut', 'notes'];

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function estConfirme(): bool
    {
        return $this->statut === self::STATUT_CONFIRME;
    }

    public function estEnAttente(): bool
    {
        return $this->statut === self::STATUT_ATTENTE;
    }

    public function estAnnule(): bool
    {
        return $this->statut === self::STATUT_ANNULE;
    }
}
