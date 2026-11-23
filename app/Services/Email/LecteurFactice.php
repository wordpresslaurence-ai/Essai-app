<?php

namespace App\Services\Email;

use App\Models\BoiteEmail;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Lecteur d'e-mails factice pour les tests (aucun accès réseau).
 *
 * - `$messages` : messages renvoyés pour n'importe quelle boîte.
 * - `$parBoite` : messages spécifiques par adresse de boîte (prioritaire).
 */
class LecteurFactice implements LecteurEmails
{
    /**
     * @param  list<MessageEmail>  $messages
     * @param  array<string, list<MessageEmail>>  $parBoite
     */
    public function __construct(
        private array $messages = [],
        private bool $connexionOk = true,
        private array $parBoite = [],
    ) {}

    public function tester(BoiteEmail $boite): bool
    {
        if (! $this->connexionOk) {
            throw new RuntimeException('Connexion factice échouée.');
        }

        return true;
    }

    public function rechercherAdresse(BoiteEmail $boite, string $adresse): array
    {
        $source = $this->parBoite[$boite->email] ?? $this->messages;
        $adresse = mb_strtolower($adresse);

        return array_values(array_filter($source, fn (MessageEmail $m) => mb_strtolower($m->expediteur) === $adresse
            || in_array($adresse, array_map('mb_strtolower', $m->destinataires), true)));
    }

    public function recupererDepuis(BoiteEmail $boite, ?CarbonInterface $depuis): array
    {
        $source = $this->parBoite[$boite->email] ?? $this->messages;

        if ($depuis === null) {
            return array_values($source);
        }

        return array_values(array_filter(
            $source,
            fn (MessageEmail $m) => $m->date->greaterThanOrEqualTo($depuis),
        ));
    }
}
