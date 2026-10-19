<?php

namespace App\Services\IA;

use App\Models\Contact;

/**
 * Assistant IA factice pour les tests (aucun appel réseau, réponses déterministes).
 */
class AssistantFactice implements AssistantIA
{
    /**
     * @param  list<string>  $suggestions
     */
    public function __construct(
        private ?ResultatAnalyse $resultat = null,
        private array $suggestions = [],
    ) {}

    public function analyser(Contact $contact): ResultatAnalyse
    {
        return $this->resultat ?? new ResultatAnalyse(
            resume: 'Résumé factice pour '.$contact->nomComplet().'.',
            verdict: 'À relancer.',
            delaiConseille: 'sous 2 jours',
            brouillon: 'Bonjour, je reviens vers vous… (brouillon factice)',
            modele: 'factice',
        );
    }

    public function suggestions(array $contexte): array
    {
        return $this->suggestions;
    }
}
