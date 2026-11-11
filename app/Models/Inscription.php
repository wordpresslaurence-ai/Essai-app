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

    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    protected $fillable = ['seance_id', 'contact_id', 'statut', 'notes', 'presence', 'suivi_cree_le', 'suivi_envoye_le'];

    protected function casts(): array
    {
        return [
            'suivi_cree_le' => 'datetime',
            'suivi_envoye_le' => 'datetime',
        ];
    }

    public function estAbsent(): bool
    {
        return $this->presence === self::ABSENT;
    }

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
