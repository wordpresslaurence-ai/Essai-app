<?php

namespace App\Services\Email;

/**
 * Compte-rendu d'une synchronisation des e-mails (toutes boîtes confondues).
 */
class ResultatSynchronisation
{
    public function __construct(
        public int $emailsImportes = 0,
        public int $tachesCreees = 0,
        public int $ignores = 0,
        public int $boitesLues = 0,
    ) {}

    /** @var list<array{nom: string, objet: string, direction: string}> Détail des mails rattachés. */
    public array $imports = [];

    /** @var list<array{adresse: string, objet: string}> Détail des prospects à créer. */
    public array $prospects = [];

    /** Enregistre un mail rattaché (et incrémente le compteur). */
    public function ajouterImport(string $nom, ?string $objet, string $direction): void
    {
        $this->emailsImportes++;
        $this->imports[] = [
            'nom' => $nom,
            'objet' => filled($objet) ? $objet : '(sans objet)',
            'direction' => $direction,
        ];
    }

    /** Enregistre un prospect à créer (et incrémente le compteur). */
    public function ajouterProspect(string $adresse, ?string $objet): void
    {
        $this->tachesCreees++;
        $this->prospects[] = [
            'adresse' => $adresse,
            'objet' => filled($objet) ? $objet : '(sans objet)',
        ];
    }

    /** Résumé lisible pour l'utilisatrice. */
    public function resume(): string
    {
        if ($this->boitesLues === 0) {
            return 'Aucune boîte e-mail active : ajoute une boîte dans les Réglages.';
        }

        $parts = [];
        $parts[] = $this->boitesLues.' boîte(s) lue(s)';
        $parts[] = $this->emailsImportes.' e-mail(s) importé(s)';
        if ($this->tachesCreees > 0) {
            $parts[] = $this->tachesCreees.' tâche(s) « créer le prospect »';
        }

        return ucfirst(implode(', ', $parts)).'.';
    }
}
