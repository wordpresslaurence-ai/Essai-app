<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une campagne de prospection : paramètres validés + copie figée de l'offre.
 * Le CRM en génère un « brief » à coller dans ChatGPT (Option B), puis importe
 * le résultat trié.
 */
class RechercheProspection extends Model
{
    protected $table = 'recherches_prospection';

    /** Sources de recherche proposées. */
    public const SOURCES = [
        'web' => 'Web (recherche publique)',
        'linkedin' => 'LinkedIn',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
    ];

    protected $fillable = [
        'nom', 'categorie_id', 'offre_copie', 'sources', 'nombre', 'role',
        'personnes_recherchees', 'zones', 'langues', 'criteres_obligatoires',
        'criteres_souhaitables', 'profils_privilegier', 'profils_eviter',
        'contact_requis', 'exclusions', 'enrichissement', 'instructions', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'nombre' => 'integer',
            'enrichissement' => 'boolean',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    /** Libellés lisibles des sources choisies. @return list<string> */
    public function libellesSources(): array
    {
        return collect($this->sources ?? [])
            ->map(fn ($s) => self::SOURCES[$s] ?? $s)
            ->all();
    }
}
