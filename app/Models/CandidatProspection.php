<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un candidat issu d'une recherche de prospection. Zone de préparation : ce
 * n'est PAS un contact du CRM tant qu'il n'a pas été importé.
 */
class CandidatProspection extends Model
{
    protected $table = 'candidats_prospection';

    protected $fillable = [
        'recherche_prospection_id', 'donnees',
        'nom', 'email', 'telephone', 'url_profil', 'origine', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'donnees' => 'array',
        ];
    }

    public function recherche(): BelongsTo
    {
        return $this->belongsTo(RechercheProspection::class, 'recherche_prospection_id');
    }

    /** Valeur d'un champ des 40 colonnes (ou null). */
    public function champ(string $cle): ?string
    {
        $valeur = $this->donnees[$cle] ?? null;

        return filled($valeur) ? (string) $valeur : null;
    }
}
