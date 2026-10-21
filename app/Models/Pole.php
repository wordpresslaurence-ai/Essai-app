<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pôle : grand domaine d'activité (Prana, Digital…), créé par l'utilisatrice.
 */
class Pole extends Model
{
    protected $fillable = ['nom', 'couleur', 'ordre', 'liens_offre'];

    /** Les catégories de ce pôle. */
    public function categories(): HasMany
    {
        return $this->hasMany(Categorie::class)->orderBy('ordre')->orderBy('nom');
    }

    /** Les boîtes e-mail rattachées à ce pôle (brique 009). */
    public function boitesEmail(): HasMany
    {
        return $this->hasMany(BoiteEmail::class);
    }
}
