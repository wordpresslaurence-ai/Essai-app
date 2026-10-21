<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un e-mail synchronisé depuis la boîte, rattaché à un contact connu.
 */
class Email extends Model
{
    public const DIRECTION_ENTRANT = 'entrant';

    public const DIRECTION_SORTANT = 'sortant';

    protected $fillable = [
        'contact_id',
        'direction',
        'de',
        'a',
        'objet',
        'extrait',
        'corps',
        'date_email',
        'message_id',
        'fil_id',
        'boite',
        'boite_email_id',
        'traite',
    ];

    protected function casts(): array
    {
        return [
            'date_email' => 'datetime',
            'traite' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** La boîte d'origine de l'e-mail (brique 009), si connue. */
    public function boiteEmail(): BelongsTo
    {
        return $this->belongsTo(BoiteEmail::class);
    }

    public function estEntrant(): bool
    {
        return $this->direction === self::DIRECTION_ENTRANT;
    }
}
