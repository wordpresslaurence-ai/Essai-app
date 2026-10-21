<?php

namespace App\Services\Email;

use App\Models\BoiteEmail;
use Carbon\CarbonInterface;

/**
 * Contrat de lecture d'une boîte e-mail. Implémenté par le vrai client IMAP
 * (production) et par un lecteur factice (tests). Chaque opération porte sur
 * une boîte précise (brique 009 : plusieurs boîtes possibles).
 */
interface LecteurEmails
{
    /**
     * Vérifie que la connexion à la boîte donnée est possible.
     * Lève une exception avec un message clair en cas d'échec.
     */
    public function tester(BoiteEmail $boite): bool;

    /**
     * Récupère les messages reçus/envoyés de la boîte depuis la date donnée
     * (ou l'historique borné côté implémentation si null).
     *
     * @return list<MessageEmail>
     */
    public function recupererDepuis(BoiteEmail $boite, ?CarbonInterface $depuis): array;
}
