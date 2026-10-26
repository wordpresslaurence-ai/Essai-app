<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adresse e-mail secondaire d'un contact. L'adresse principale reste portée
 * par contacts.email ; ce modèle couvre les adresses supplémentaires.
 */
class AdresseEmail extends Model
{
    protected $table = 'adresses_email';

    protected $fillable = ['contact_id', 'adresse'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
