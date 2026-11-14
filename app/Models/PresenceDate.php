<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Présence d'un inscrit à une date d'un cycle, et envoi de l'enregistrement s'il était absent. */
class PresenceDate extends Model
{
    protected $table = 'presences_date';

    protected $fillable = ['seance_date_id', 'inscription_id', 'presence', 'enregistrement_envoye_le'];

    protected function casts(): array
    {
        return ['enregistrement_envoye_le' => 'datetime'];
    }

    public function seanceDate(): BelongsTo
    {
        return $this->belongsTo(SeanceDate::class);
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }
}
