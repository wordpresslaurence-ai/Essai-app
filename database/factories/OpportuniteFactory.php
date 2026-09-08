<?php

namespace Database\Factories;

use App\Models\Opportunite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunite>
 */
class OpportuniteFactory extends Factory
{
    protected $model = Opportunite::class;

    public function definition(): array
    {
        return [
            'titre' => fake()->sentence(3),
            'contact_id' => null,
            'montant' => fake()->randomFloat(2, 500, 20000),
            'etape' => Opportunite::ETAPE_NOUVEAU,
            'notes' => null,
        ];
    }

    public function etape(string $etape): static
    {
        return $this->state(fn () => ['etape' => $etape]);
    }
}
