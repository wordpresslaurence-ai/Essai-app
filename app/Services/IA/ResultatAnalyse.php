<?php

namespace App\Services\IA;

/**
 * Résultat d'une analyse de contact par l'assistant : résumé, verdict de relance,
 * délai conseillé, brouillon et — brique 010 — champs enrichis (intention, infos
 * clés, température/étape conseillées, promesses, rendez-vous). Indépendant du fournisseur.
 */
class ResultatAnalyse
{
    /**
     * @param  array<string, mixed>  $infosCles
     * @param  list<array{texte: string, echeance: ?string}>  $promesses
     * @param  array<string, mixed>  $rdv
     */
    public function __construct(
        public readonly string $resume,
        public readonly string $verdict,
        public readonly ?string $delaiConseille,
        public readonly string $brouillon,
        public readonly ?string $modele = null,
        public readonly ?string $intention = null,
        public readonly ?string $temperatureConseillee = null,
        public readonly ?string $etapeConseillee = null,
        public readonly array $infosCles = [],
        public readonly array $promesses = [],
        public readonly array $rdv = [],
    ) {}
}
