<?php

namespace Database\Factories;

use App\Models\BoiteEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoiteEmail>
 */
class BoiteEmailFactory extends Factory
{
    protected $model = BoiteEmail::class;

    public function definition(): array
    {
        return [
            'libelle' => null,
            'fournisseur' => BoiteEmail::FOURNISSEUR_GMAIL,
            'email' => fake()->unique()->safeEmail(),
            'mot_de_passe' => 'mot-de-passe-app',
            'imap_host' => null,
            'imap_port' => 993,
            'dossier_envoyes' => null,
            'pole_id' => null,
            'active' => true,
            'derniere_synchro_at' => null,
        ];
    }

    public function hostinger(): static
    {
        return $this->state(fn () => ['fournisseur' => BoiteEmail::FOURNISSEUR_HOSTINGER]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
