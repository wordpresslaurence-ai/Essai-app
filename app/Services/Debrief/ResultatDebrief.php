<?php

namespace App\Services\Debrief;

use Illuminate\Support\Carbon;

/**
 * Résultat de l'analyse d'un débrief d'appel collé (sortie conversationnelle
 * de ChatGPT, au format fixe imposé par GenerateurPromptDebrief). Toutes les
 * valeurs sont optionnelles : une information absente reste nulle.
 */
class ResultatDebrief
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $resume = null,
        public readonly ?int $capacite = null,
        public readonly ?string $tarif = null,
        public readonly ?string $disponibilites = null,
        public readonly ?string $besoins = null,
        public readonly ?string $objections = null,
        public readonly ?string $promis = null,
        public readonly ?string $prochaineAction = null,
        public readonly ?Carbon $echeance = null,
        public readonly string $texteComplet = '',
    ) {}

    /** Le débrief contient-il au moins une information exploitable ? */
    public function estVide(): bool
    {
        return $this->resume === null
            && $this->capacite === null
            && $this->tarif === null
            && $this->besoins === null
            && $this->objections === null
            && $this->promis === null
            && $this->prochaineAction === null;
    }
}
