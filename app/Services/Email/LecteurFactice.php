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

    public function diagnostic(BoiteEmail $boite): array
    {
        $source = $this->parBoite[$boite->email] ?? $this->messages;
        $recents = array_filter($source, fn (MessageEmail $m) => $m->date->greaterThanOrEqualTo(now()->subDays(30)));

        return [
            ['dossier' => 'INBOX', 'envoi' => false, 'lu' => true, 'ouvert' => true, 'messages_30j' => count(array_filter($recents, fn ($m) => ! $m->envoye))],
            ['dossier' => 'INBOX.Sent', 'envoi' => true, 'lu' => true, 'ouvert' => true, 'messages_30j' => count(array_filter($recents, fn ($m) => $m->envoye))],
        ];
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
