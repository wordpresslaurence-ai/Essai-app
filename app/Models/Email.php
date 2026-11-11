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

    /**
     * Un nouvel e-mail (reçu ou envoyé) rouvre la conversation : le suivi des
     * relances du contact, s'il était en pause, reprend automatiquement.
     */
    protected static function booted(): void
    {
        static::created(function (Email $email) {
            if ($email->contact_id) {
                Contact::whereKey($email->contact_id)
                    ->whereNotNull('suivi_pause_le')
                    ->update(['suivi_pause_le' => null]);
            }
        });
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
