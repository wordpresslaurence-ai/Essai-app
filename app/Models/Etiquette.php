<?php

namespace App\Models;

use Database\Factories\EtiquetteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Une étiquette réutilisable pour classer les contacts (EF-11).
 */
class Etiquette extends Model
{
    /** @use HasFactory<EtiquetteFactory> */
    use HasFactory;

    protected $fillable = [
        'nom',
        'couleur',
    ];

    /** Les contacts portant cette étiquette. */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }
}
