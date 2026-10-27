<?php

namespace App\Services\Prospection;

/**
 * Analyse le CSV collé (produit par ChatGPT au format du brief) et en extrait
 * des candidats bruts. Aucune normalisation métier ici : on conserve ce que
 * ChatGPT a fourni ; le mapping vers un contact se fera à l'import (tranche 4).
 */
class AnalyseurCsv
{
    /**
     * @return array{candidats: list<array<string,string>>, total: int, ignores: int, entetes_inconnues: list<string>}
     */
    public function analyser(string $contenu): array
    {
        $contenu = $this->sansBom($contenu);

        $lignes = $this->lireLignes($contenu);
        if ($lignes === []) {
            return ['candidats' => [], 'total' => 0, 'ignores' => 0, 'entetes_inconnues' => []];
        }

        $connus = explode(';', GenerateurBrief::ENTETES_CSV);
        $entetes = array_shift($lignes);

        // index de colonne -> clé de champ (si reconnue)
        $map = [];
        $inconnues = [];
        foreach ($entetes as $i => $entete) {
            $cle = mb_strtolower(trim((string) $entete));
            if (in_array($cle, $connus, true)) {
                $map[$i] = $cle;
            } elseif ($cle !== '') {
                $inconnues[] = (string) $entete;
            }
        }

        $candidats = [];
        $ignores = 0;
        foreach ($lignes as $ligne) {
            $donnees = [];
            foreach ($map as $i => $champ) {
                $valeur = isset($ligne[$i]) ? trim((string) $ligne[$i]) : '';
                if ($valeur !== '') {
                    $donnees[$champ] = $valeur;
                }
            }

            // Une ligne sans nom n'est pas un candidat exploitable.
            if (blank($donnees['nom'] ?? null)) {
                if (array_filter($donnees) !== []) {
                    $ignores++;
                }

                continue;
            }

            $candidats[] = $donnees;
        }

        return [
            'candidats' => $candidats,
            'total' => count($candidats),
            'ignores' => $ignores,
            'entetes_inconnues' => array_values(array_unique($inconnues)),
        ];
    }

    /** Retire un éventuel BOM UTF-8 en tête. */
    private function sansBom(string $contenu): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? $contenu;
    }

    /**
     * Lit toutes les lignes (en-tête inclus), séparateur ; ou , détecté sur la
     * 1re ligne. Gère les champs entre guillemets et les retours à la ligne.
     *
     * @return array<int, array<int, string>>
     */
    private function lireLignes(string $contenu): array
    {
        $contenu = trim($contenu);
        if ($contenu === '') {
            return [];
        }

        $premiereLigne = strtok($contenu, "\n");
        $separateur = substr_count((string) $premiereLigne, ';') >= substr_count((string) $premiereLigne, ',') ? ';' : ',';

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        $lignes = [];
        while (($donnees = fgetcsv($flux, 0, $separateur)) !== false) {
            // fgetcsv rend [null] sur une ligne vide : on l'ignore.
            if ($donnees === [null]) {
                continue;
            }
            $lignes[] = array_map(fn ($v) => trim((string) $v), $donnees);
        }
        fclose($flux);

        return $lignes;
    }
}
