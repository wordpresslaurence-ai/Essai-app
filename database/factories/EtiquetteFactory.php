<?php

namespace Database\Factories;

use App\Models\Etiquette;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Etiquette>
 */
class EtiquetteFactory extends Factory
{
    protected $model = Etiquette::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->word(),
            'couleur' => fake()->hexColor(),
        ];
    }
}
