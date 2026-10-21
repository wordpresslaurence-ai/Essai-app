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
        private ?ResultatProspection $prospection = null,
    ) {}

    public function analyser(Contact $contact): ResultatAnalyse
    {
        return $this->resultat ?? new ResultatAnalyse(
            resume: 'Résumé factice pour '.$contact->nomComplet().'.',
            verdict: 'À relancer.',
            delaiConseille: 'sous 2 jours',
            brouillon: 'Bonjour, je reviens vers vous… (brouillon factice)',
            modele: 'factice',
            intention: 'question',
            temperatureConseillee: 'moyen',
            etapeConseillee: 'qualifie',
            infosCles: ['budget' => null, 'delai' => null, 'dates_rdv' => [], 'decisions' => null],
            promesses: [],
            rdv: ['demande' => false, 'date' => null, 'details' => null],
        );
    }

    public function redigerProspection(Contact $contact, ?string $angle = null): ResultatProspection
    {
        return $this->prospection ?? new ResultatProspection(
            objet: 'Découvrir la formation Prana Vidya',
            corps: 'Bonjour '.$contact->nomComplet().', (brouillon de prospection factice)'
                .($angle ? ' — '.$angle : ''),
            modele: 'factice',
        );
    }

    public function suggestions(array $contexte): array
    {
        return $this->suggestions;
    }
}
