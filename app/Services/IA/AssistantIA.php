<?php

namespace App\Services\IA;

use App\Models\Contact;

/**
 * Contrat de l'assistant IA. Implémenté par le client Claude (production) et par
 * un assistant factice (tests). Il propose, il n'envoie jamais rien.
 */
interface AssistantIA
{
    /**
     * Analyse le fil d'échanges d'un contact : résumé, faut-il relancer, brouillon.
     */
    public function analyser(Contact $contact): ResultatAnalyse;

    /**
     * Rédige un brouillon de mail de prospection (objet + corps) vers un prof de
     * yoga, à partir du pitch des réglages et du contact. Ne l'envoie jamais.
     */
    public function redigerProspection(Contact $contact, ?string $angle = null): ResultatProspection;

    /**
     * Rédige un brouillon de RÉPONSE au dernier e-mail reçu du contact (objet +
     * corps), à partir du fil d'échanges. Ne l'envoie jamais.
     */
    public function redigerReponse(Contact $contact): ResultatProspection;

    /**
     * Suggestions proactives pour le brief du matin, à partir d'un contexte agrégé.
     *
     * @param  array<string, mixed>  $contexte
     * @return list<string>
     */
    public function suggestions(array $contexte): array;
}
