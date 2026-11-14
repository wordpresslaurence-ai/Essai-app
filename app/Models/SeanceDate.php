<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une date d'une session (un cycle en compte plusieurs), avec son lien de visio
 * et, après coup, le lien de l'enregistrement.
 */
class SeanceDate extends Model
{
    protected $table = 'seance_dates';

    protected $fillable = ['seance_id', 'date_debut', 'lien_visio', 'lien_enregistrement', 'rappel_cree_le'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'rappel_cree_le' => 'datetime',
        ];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(PresenceDate::class);
    }

    /** « lundi 12 janvier 2027 à 10 h 30 » (sans l'heure si elle n'est pas précisée). */
    public function libelle(): string
    {
        $heure = ModeleMessage::heureLisible($this->date_debut);

        return $this->date_debut->translatedFormat('l j F Y').($heure !== '' ? ' à '.$heure : '');
    }
}
