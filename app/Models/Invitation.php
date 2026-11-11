<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation d'un contact à une session : suivi de la campagne
 * (à inviter → invité → relancé, a répondu, a décliné).
 */
class Invitation extends Model
{
    public const STATUT_A_INVITER = 'a_inviter';

    public const STATUT_INVITEE = 'invitee';

    public const STATUT_RELANCEE = 'relancee';

    public const STATUT_A_REPONDU = 'a_repondu';

    public const STATUT_DECLINEE = 'declinee';

    /** Libellé et couleurs (fond, texte) de chaque statut. */
    public const STATUTS = [
        self::STATUT_A_INVITER => ['À inviter', 'var(--gold-tint)', 'var(--accent)'],
        self::STATUT_INVITEE => ['Invité', 'var(--lav-tint)', 'var(--ink)'],
        self::STATUT_RELANCEE => ['Relancé', 'var(--lav-tint)', 'var(--ink)'],
        self::STATUT_A_REPONDU => ['A répondu', 'var(--mint-tint)', 'var(--mint-deep)'],
        self::STATUT_DECLINEE => ['A décliné', 'var(--terra-tint)', 'var(--terra)'],
    ];

    /** Délai (jours) après lequel une invitation sans suite est « à relancer ». */
    public const DELAI_RELANCE_JOURS = 5;

    protected $fillable = ['seance_id', 'contact_id', 'statut', 'modele_message_id', 'invitee_le', 'relancee_le'];

    /** Une invitation créée sans statut est « à inviter » (même avant relecture en base). */
    protected $attributes = ['statut' => self::STATUT_A_INVITER];

    protected function casts(): array
    {
        return [
            'seance_id' => 'integer',
            'contact_id' => 'integer',
            'modele_message_id' => 'integer',
            'invitee_le' => 'datetime',
            'relancee_le' => 'datetime',
        ];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModeleMessage::class, 'modele_message_id');
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut][0] ?? $this->statut;
    }

    /** Invitée une première fois, sans réponse depuis le délai de relance. */
    public function estARelancer(): bool
    {
        return $this->statut === self::STATUT_INVITEE
            && $this->invitee_le !== null
            && $this->invitee_le->lte(now()->subDays(self::DELAI_RELANCE_JOURS));
    }
}
