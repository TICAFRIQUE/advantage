<?php

namespace Database\Factories;

use App\Enums\StatutPartenaire;
use App\Models\Partenaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partenaire>
 */
class PartenaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'secteur' => fake()->randomElement(['Restauration', 'Santé', 'Mode', 'Hôtellerie', 'Beauté', 'Supermarché']),
            'localisation' => fake()->randomElement(['Cocody', 'Plateau', 'Marcory', 'Yopougon', 'Treichville']).', Abidjan',
            'contact' => fake()->numerify('07########'),
            'taux_reduction' => fake()->randomElement([5, 10, 15, 20]),
            'statut' => StatutPartenaire::Actif,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutPartenaire::Inactif,
        ]);
    }
}
