<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat d'une « Analyse & relances » produite par l'assistant pour un contact.
 */
class Analyse extends Model
{
    protected $fillable = [
        'contact_id',
        'resume',
        'verdict',
        'delai_conseille',
        'brouillon',
        'modele',
        'faite_le',
    ];

    protected function casts(): array
    {
        return [
            'faite_le' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
