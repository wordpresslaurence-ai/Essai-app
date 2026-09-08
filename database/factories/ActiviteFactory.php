<?php

namespace Database\Factories;

use App\Models\Activite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activite>
 */
class ActiviteFactory extends Factory
{
    protected $model = Activite::class;

    public function definition(): array
    {
        return [
            'titre' => fake()->sentence(3),
            'type' => Activite::TYPE_TACHE,
            'echeance' => now()->addDay(),
            'terminee_at' => null,
        ];
    }

    public function terminee(): static
    {
        return $this->state(fn () => ['terminee_at' => now()]);
    }

    public function echeance($date): static
    {
        return $this->state(fn () => ['echeance' => $date]);
    }
}
