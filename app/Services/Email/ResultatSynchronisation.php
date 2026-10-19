<?php

namespace App\Services\Email;

/**
 * Compte-rendu d'une synchronisation des e-mails.
 */
class ResultatSynchronisation
{
    public function __construct(
        public int $emailsImportes = 0,
        public int $tachesCreees = 0,
        public int $ignores = 0,
    ) {}

    /** Résumé lisible pour l'utilisatrice. */
    public function resume(): string
    {
        $parts = [];
        $parts[] = $this->emailsImportes.' e-mail(s) importé(s)';
        if ($this->tachesCreees > 0) {
            $parts[] = $this->tachesCreees.' tâche(s) « créer le prospect »';
        }

        return ucfirst(implode(', ', $parts)).'.';
    }
}
