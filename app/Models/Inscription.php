<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $fillable = ['seance_id', 'contact_id', 'statut', 'notes', 'presence', 'suivi_cree_le', 'suivi_envoye_le',
        'montant_du', 'avis_demande_le', 'avis_recu_le', 'avis_texte', 'avis_publiable'];

    protected function casts(): array
    {
        return [
            'suivi_cree_le' => 'datetime',
            'suivi_envoye_le' => 'datetime',
            'montant_du' => 'float',
            'avis_demande_le' => 'datetime',
            'avis_recu_le' => 'datetime',
            'avis_publiable' => 'boolean',
        ];
    }

    public function estAbsent(): bool
    {
        return $this->presence === self::ABSENT;
    }

    // --- Paiements (brique 015) ----------------------------------------------

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /** Montant attendu : celui propre à la personne, sinon le prix de la session. Null = non défini. */
    public function montantDu(): ?float
    {
        return $this->montant_du ?? $this->seance?->prixApplique();
    }

    public function totalPaye(): float
    {
        return round((float) $this->paiements->sum('montant'), 2);
    }

    /** Reste à payer (jamais négatif) ; null si aucun montant n'est attendu. */
    public function resteDu(): ?float
    {
        $du = $this->montantDu();

        return $du === null ? null : max(0, round($du - $this->totalPaye(), 2));
    }

    /** solde | partiel | impaye | indefini (aucun prix connu). */
    public function etatPaiement(): string
    {
        $du = $this->montantDu();
        $paye = $this->totalPaye();

        if ($du === null) {
            return $paye > 0 ? 'solde' : 'indefini';
        }
        if ($paye >= $du - 0.005) {
            return 'solde';
        }

        return $paye > 0 ? 'partiel' : 'impaye';
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
