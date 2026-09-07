<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide un numéro d'entreprise belge (BCE / KBO), cf. EF-15.
 *
 * Règle : 10 chiffres, commençant par 0 ou 1, avec clé de contrôle modulo 97
 * (les 2 derniers chiffres = 97 − (les 8 premiers mod 97)).
 * La saisie est normalisée : points, espaces et préfixe « BE » sont ignorés.
 */
class NumeroEntrepriseBe implements ValidationRule
{
    /**
     * Extrait les 10 chiffres d'une saisie, ou null si le format est invalide.
     */
    public static function normaliser(string $valeur): ?string
    {
        $chiffres = preg_replace('/[^0-9]/', '', $valeur);

        if (strlen((string) $chiffres) !== 10) {
            return null;
        }

        return $chiffres;
    }

    /**
     * Vérifie qu'une chaîne de 10 chiffres respecte la clé de contrôle belge.
     */
    public static function estValide(string $dixChiffres): bool
    {
        if (! preg_match('/^[01][0-9]{9}$/', $dixChiffres)) {
            return false;
        }

        $base = (int) substr($dixChiffres, 0, 8);
        $cle = (int) substr($dixChiffres, 8, 2);

        return (97 - ($base % 97)) === $cle;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $chiffres = self::normaliser((string) $value);

        if ($chiffres === null || ! self::estValide($chiffres)) {
            $fail('validation.custom.numero_entreprise.invalide')->translate();
        }
    }
}
