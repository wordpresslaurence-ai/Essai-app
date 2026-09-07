<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide un numéro de TVA belge, cf. EF-16.
 *
 * Règle : préfixe « BE » (facultatif à la saisie) + un numéro d'entreprise valide
 * (mêmes 10 chiffres et même clé de contrôle que la BCE). Si le champ
 * `numero_entreprise` est aussi renseigné, la cohérence entre les deux est vérifiée.
 */
class NumeroTvaBe implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $chiffres = NumeroEntrepriseBe::normaliser((string) $value);

        if ($chiffres === null || ! NumeroEntrepriseBe::estValide($chiffres)) {
            $fail('validation.custom.numero_tva.invalide')->translate();

            return;
        }

        // Cohérence avec le numéro d'entreprise s'il est renseigné.
        $numeroEntreprise = $this->data['numero_entreprise'] ?? null;

        if (filled($numeroEntreprise)) {
            $chiffresEntreprise = NumeroEntrepriseBe::normaliser((string) $numeroEntreprise);

            if ($chiffresEntreprise !== null && $chiffresEntreprise !== $chiffres) {
                $fail('validation.custom.numero_tva.invalide')->translate();
            }
        }
    }
}
