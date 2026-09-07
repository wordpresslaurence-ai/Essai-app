<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'type' => Contact::TYPE_PERSONNE,
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => fake()->phoneNumber(),
            'fonction' => fake()->jobTitle(),
            'adresse_pays' => 'BE',
        ];
    }

    /** Une entreprise plutôt qu'une personne. */
    public function entreprise(): static
    {
        return $this->state(fn () => [
            'type' => Contact::TYPE_ENTREPRISE,
            'nom' => fake()->company(),
            'prenom' => null,
            'fonction' => null,
            'site_web' => fake()->url(),
            'secteur' => fake()->word(),
        ]);
    }

    /** Un contact archivé. */
    public function archive(): static
    {
        return $this->state(fn () => [
            'archived_at' => now(),
        ]);
    }
}
