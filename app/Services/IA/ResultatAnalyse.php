<?php

namespace App\Services\IA;

/**
 * Résultat d'une analyse de contact par l'assistant : résumé, verdict de relance,
 * délai conseillé et brouillon de message. Indépendant du fournisseur.
 */
class ResultatAnalyse
{
    public function __construct(
        public readonly string $resume,
        public readonly string $verdict,
        public readonly ?string $delaiConseille,
        public readonly string $brouillon,
        public readonly ?string $modele = null,
    ) {}
}
