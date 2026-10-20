<?php

namespace App\Services\IA;

/**
 * Résultat d'une rédaction de mail de prospection : objet + corps, prêts à
 * relire et copier. Indépendant du fournisseur. L'assistant n'envoie jamais rien.
 */
class ResultatProspection
{
    public function __construct(
        public readonly string $objet,
        public readonly string $corps,
        public readonly ?string $modele = null,
    ) {}
}
