<?php

namespace App\Services\Email;

use Carbon\CarbonInterface;

/**
 * Contrat de lecture d'une boîte e-mail. Implémenté par le vrai client IMAP
 * (production) et par un lecteur factice (tests).
 */
interface LecteurEmails
{
    /**
     * Vérifie que la connexion est possible avec les réglages actuels.
     * Lève une exception avec un message clair en cas d'échec.
     */
    public function tester(): bool;

    /**
     * Récupère les messages reçus/envoyés depuis la date donnée (ou tout
     * l'historique disponible borné côté implémentation si null).
     *
     * @return list<MessageEmail>
     */
    public function recupererDepuis(?CarbonInterface $depuis): array;
}
