<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un versement reçu pour une inscription (acompte, solde…). Le CRM ne fait que
 * noter ce qui a été reçu : il n'encaisse rien et ne se connecte à aucune banque.
 */
class Paiement extends Model
{
    public const MODES = [
        'virement' => 'Virement',
        'en_ligne' => 'Paiement en ligne',
        'especes' => 'Espèces',
        'autre' => 'Autre',
    ];

    protected $fillable = ['inscription_id', 'montant', 'paye_le', 'mode', 'note'];

    protected function casts(): array
    {
        return [
            'montant' => 'float',
            'paye_le' => 'date',
        ];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function libelleMode(): string
    {
        return self::MODES[$this->mode] ?? '';
    }

    /** « 285 € » ou « 142,50 € ». */
    public static function euros(float $montant): string
    {
        $entier = abs($montant - round($montant)) < 0.005;

        return number_format($montant, $entier ? 0 : 2, ',', ' ').' €';
    }
}
