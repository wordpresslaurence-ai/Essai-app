<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie : subdivision d'un pôle (Formation profs, Recherche de salle, Sites web…).
 */
class Categorie extends Model
{
    protected $fillable = ['pole_id', 'nom', 'couleur', 'prix', 'ordre', 'offre', 'liens', 'masquer_par_defaut'];

    protected function casts(): array
    {
        return [
            'liens' => 'array',
            'masquer_par_defaut' => 'boolean',
            'prix' => 'decimal:2',
        ];
    }

    public function pole(): BelongsTo
    {
        return $this->belongsTo(Pole::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function opportunites(): HasMany
    {
        return $this->hasMany(Opportunite::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    /** Libellé « Pôle · Catégorie ». */
    public function libelleComplet(): string
    {
        return ($this->pole?->nom ? $this->pole->nom.' · ' : '').$this->nom;
    }
}
