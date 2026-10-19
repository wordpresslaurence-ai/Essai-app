<?php

namespace App\Services\Email;

use Carbon\CarbonInterface;

/**
 * Un message e-mail brut tel que lu dans la boîte, avant rattachement à un contact.
 * Objet simple et immuable (indépendant de la librairie IMAP utilisée).
 */
class MessageEmail
{
    /**
     * @param  list<string>  $destinataires  adresses du champ « À »
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $expediteur,       // adresse « De »
        public readonly array $destinataires,     // adresses « À »
        public readonly ?string $objet,
        public readonly ?string $texte,           // corps en texte simple
        public readonly CarbonInterface $date,
        public readonly ?string $filId = null,    // identifiant de conversation
    ) {}

    /** Aperçu court du corps (pour l'affichage en liste). */
    public function extrait(int $longueur = 200): ?string
    {
        if ($this->texte === null) {
            return null;
        }

        $texte = trim(preg_replace('/\s+/', ' ', $this->texte) ?? '');

        return mb_strlen($texte) > $longueur
            ? mb_substr($texte, 0, $longueur).'…'
            : $texte;
    }
}
