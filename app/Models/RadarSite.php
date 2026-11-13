<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dernier examen du site web d'un contact (radar de prospection WordPress).
 */
class RadarSite extends Model
{
    public const A_AMELIORER = 'a_ameliorer';

    public const RESEAU_SEUL = 'reseau_seul';

    public const SANS_SITE = 'sans_site';

    public const INJOIGNABLE = 'injoignable';

    public const NON_ANALYSE = 'non_analyse';

    public const CORRECT = 'correct';

    /** Statut => libellé, dans l'ordre d'intérêt pour la prospection. */
    public const STATUTS = [
        self::A_AMELIORER => 'Site à améliorer',
        self::RESEAU_SEUL => 'Réseaux sociaux seulement',
        self::SANS_SITE => 'Pas de site connu',
        self::INJOIGNABLE => 'Site injoignable',
        self::NON_ANALYSE => 'À analyser',
        self::CORRECT => 'Site correct',
    ];

    protected $table = 'radars_site';

    protected $fillable = ['contact_id', 'url', 'url_finale', 'statut', 'techno', 'constats', 'duree_ms', 'analyse_le', 'ecarte_le'];

    protected function casts(): array
    {
        return [
            'constats' => 'array',
            'duree_ms' => 'integer',
            'analyse_le' => 'datetime',
            'ecarte_le' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Statut à afficher pour un contact : celui du dernier examen s'il porte
     * toujours sur l'adresse de la fiche ; sinon « pas de site connu » ou « à analyser ».
     */
    public static function statutPour(Contact $contact): string
    {
        $radar = $contact->radarSite;

        if (blank($contact->site_web)) {
            return self::SANS_SITE;
        }

        if ($radar && $radar->analyse_le && $radar->url === $contact->site_web) {
            return $radar->statut;
        }

        return self::NON_ANALYSE;
    }

    /** @return array<int, string> Les constats, en phrases. */
    public function textesConstats(): array
    {
        return array_values(array_filter(array_map(fn ($c) => $c['texte'] ?? null, $this->constats ?? [])));
    }
}
