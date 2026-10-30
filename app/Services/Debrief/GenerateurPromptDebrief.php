<?php

namespace App\Services\Debrief;

use App\Models\Contact;

/**
 * Construit le prompt à coller dans ChatGPT (conversationnel, 0 € d'API) pour
 * transformer la transcription d'un appel en débrief au format fixe attendu
 * par AnalyseurDebrief. Pré-rempli avec le contact et sa catégorie.
 */
class GenerateurPromptDebrief
{
    public function generer(Contact $contact): string
    {
        $nom = $contact->nomComplet();
        $offre = $contact->categorie?->libelleComplet();

        $l = [];
        $l[] = 'Tu es mon assistant CRM. Je te donne la TRANSCRIPTION d\'un appel.';
        $l[] = 'Rends-moi un débrief dans le format EXACT ci-dessous, rien d\'autre.';
        $l[] = '';
        $l[] = 'RÈGLES';
        $l[] = '- N\'invente RIEN. Une info absente de la transcription reste VIDE.';
        $l[] = '- Sépare le FAIT (ce qui a été dit) de ce qui reste à faire.';
        $l[] = '- Dates au format AAAA-MM-JJ. Montants avec la devise. Français. Sois bref et factuel.';
        $l[] = '';
        $l[] = 'CONTEXTE';
        $l[] = '- Contact / structure : '.($nom !== '' ? $nom : '(à préciser)');
        if ($offre) {
            $l[] = '- Offre concernée : '.$offre;
        }
        $l[] = '';
        $l[] = '=== DÉBRIEF D\'APPEL ===';
        $l[] = 'TYPE : prospection_salle | formation_eleve | partenariat | autre';
        $l[] = 'RÉSUMÉ : (2-3 phrases)';
        $l[] = 'POINTS CLÉS :';
        $l[] = '- Capacité : ';
        $l[] = '- Tarif : ';
        $l[] = '- Disponibilités : ';
        $l[] = '- Autres faits : ';
        $l[] = 'BESOINS / ATTENTES : ';
        $l[] = 'OBJECTIONS / FREINS : ';
        $l[] = 'PROMIS / DÉCIDÉ : ';
        $l[] = 'PROCHAINE ACTION : ';
        $l[] = 'ÉCHÉANCE : (AAAA-MM-JJ ou "dans X jours")';
        $l[] = '=== FIN ===';
        $l[] = '';
        $l[] = 'Voici la transcription :';
        $l[] = '[colle ici la transcription tl;dv]';

        return implode("\n", $l);
    }
}
