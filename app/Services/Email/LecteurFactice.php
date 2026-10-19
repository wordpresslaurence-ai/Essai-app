<?php

namespace App\Services\Email;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Lecteur d'e-mails factice pour les tests (aucun accès réseau).
 */
class LecteurFactice implements LecteurEmails
{
    /**
     * @param  list<MessageEmail>  $messages
     */
    public function __construct(
        private array $messages = [],
        private bool $connexionOk = true,
    ) {}

    public function tester(): bool
    {
        if (! $this->connexionOk) {
            throw new RuntimeException('Connexion factice échouée.');
        }

        return true;
    }

    public function recupererDepuis(?CarbonInterface $depuis): array
    {
        if ($depuis === null) {
            return $this->messages;
        }

        return array_values(array_filter(
            $this->messages,
            fn (MessageEmail $m) => $m->date->greaterThanOrEqualTo($depuis),
        ));
    }
}
