<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pôle : grand domaine d'activité (Prana, Digital…), créé par l'utilisatrice.
 */
class Pole extends Model
{
    protected $fillable = ['nom', 'couleur', 'ordre'];

    /** Les catégories de ce pôle. */
    public function categories(): HasMany
    {
        return $this->hasMany(Categorie::class)->orderBy('ordre')->orderBy('nom');
    }
}
