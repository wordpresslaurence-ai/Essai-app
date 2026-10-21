<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat d'une « Analyse & relances » produite par l'assistant pour un contact
 * (brique 010 : analyse enrichie — intention, infos clés, suggestions).
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
        'intention',
        'temperature_conseillee',
        'etape_conseillee',
        'infos_cles',
        'promesses',
        'rdv',
        'faite_le',
    ];

    /** Libellés lisibles des intentions détectées. */
    public const INTENTIONS = [
        'nouveau_prospect' => 'Nouveau prospect',
        'question' => 'Question',
        'demande_devis' => 'Demande de devis',
        'prise_rdv' => 'Prise de rendez-vous',
        'reclamation' => 'Réclamation',
        'remerciement' => 'Remerciement',
        'paiement' => 'Paiement',
        'relance' => 'Relance',
        'autre' => 'Autre',
    ];

    protected function casts(): array
    {
        return [
            'faite_le' => 'datetime',
            'infos_cles' => 'array',
            'promesses' => 'array',
            'rdv' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Libellé lisible de l'intention (ou null). */
    public function libelleIntention(): ?string
    {
        if (blank($this->intention)) {
            return null;
        }

        return self::INTENTIONS[$this->intention] ?? ucfirst(str_replace('_', ' ', $this->intention));
    }
}
