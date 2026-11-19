<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une publication prévue dans le calendrier de contenu d'un trimestre. */
class Contenu extends Model
{
    public const FORMATS = [
        'vocal' => 'Vocal',
        'video' => 'Vidéo',
        'texte' => 'Texte',
        'pdf' => 'Fiche PDF',
        'live' => 'Direct',
    ];

    public const STATUTS = [
        'idee' => 'Idée',
        'a_enregistrer' => 'À enregistrer',
        'pret' => 'Prêt',
        'programme' => 'Programmé',
        'publie' => 'Publié',
    ];

    /** Ce qui reste à faire avant la date prévue. */
    public const A_PREPARER = ['idee', 'a_enregistrer'];

    protected $table = 'contenus';

    protected $fillable = ['seance_id', 'date_prevue', 'format', 'titre', 'statut', 'notes', 'pratique_telegram_id'];

    protected function casts(): array
    {
        return ['date_prevue' => 'date'];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function pratique(): BelongsTo
    {
        return $this->belongsTo(PratiqueTelegram::class, 'pratique_telegram_id');
    }
}
