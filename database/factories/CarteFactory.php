<?php

namespace Database\Factories;

use App\Enums\StatutCarte;
use App\Models\Carte;
use App\Models\Titulaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Carte>
 */
class CarteFactory extends Factory
{
    /**
     * Carte active, activée aujourd'hui (expire_le est calculé par le modèle).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_carte' => fake()->unique()->numerify('#######'),
            'titulaire_id' => Titulaire::factory(),
            'active_par_id' => User::factory(),
            'active_le' => now(),
            'statut' => StatutCarte::Active,
        ];
    }

    /**
     * Carte activée il y a un certain temps (sert aux tests d'expiration).
     */
    public function activeeIlYa(int $mois, int $jours = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'active_le' => now()->subMonthsNoOverflow($mois)->subDays($jours),
        ]);
    }

    public function expiree(): static
    {
        return $this->state(fn (array $attributes) => [
            'active_le' => now()->subMonthsNoOverflow(13),
            'statut' => StatutCarte::Expiree,
        ]);
    }

    public function suspendue(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutCarte::Suspendue,
            'motif_statut' => 'Suspension administrative',
        ]);
    }

    public function revoquee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutCarte::Revoquee,
            'motif_statut' => 'Carte déclarée perdue',
        ]);
    }
}
