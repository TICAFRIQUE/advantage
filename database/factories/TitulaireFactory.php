<?php

namespace Database\Factories;

use App\Enums\StatutTitulaire;
use App\Models\Titulaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Titulaire>
 */
class TitulaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => mb_strtoupper(fake()->lastName()),
            'prenom' => fake()->firstName(),
            'telephone' => '+225'.fake()->randomElement(['01', '05', '07']).fake()->unique()->numerify('########'),
            'statut' => StatutTitulaire::Actif,
        ];
    }

    /**
     * Titulaire dont la pièce d'identité a été renseignée (facultative au MVP).
     */
    public function avecPieceIdentite(?string $numero = null): static
    {
        return $this->state(fn (array $attributes) => [
            'numero_piece_identite' => $numero ?? fake()->unique()->bothify('CI#########'),
        ]);
    }
}
