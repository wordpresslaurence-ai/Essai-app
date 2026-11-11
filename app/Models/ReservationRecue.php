<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une réservation reçue du site (FluentBooking) et ce que le CRM en a fait.
 */
class ReservationRecue extends Model
{
    public const STATUT_INSCRIT = 'inscrit';

    public const STATUT_RDV = 'rdv';

    public const STATUT_ANNULE = 'annule';

    public const STATUT_IGNORE = 'ignore';

    /** Nombre de lignes conservées dans le journal. */
    public const CONSERVER = 100;

    protected $table = 'reservations_recues';

    protected $fillable = ['statut', 'resume', 'contact_id', 'seance_id', 'contenu'];

    protected function casts(): array
    {
        return ['contact_id' => 'integer', 'seance_id' => 'integer'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }
}
