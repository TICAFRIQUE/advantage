<?php

namespace Database\Factories;

use App\Enums\StatutUtilisateur;
use App\Models\Partenaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * PIN par défaut des comptes de test.
     */
    public const PIN = '48157';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->name(),
            'nom_utilisateur' => fake()->unique()->userName(),
            'email' => null,
            'telephone' => fake()->numerify('07########'),
            'password' => static::$password ??= Hash::make(self::PIN),
            'statut' => StatutUtilisateur::Actif,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Compte verrouillé après trop d'échecs de connexion.
     */
    public function verrouille(): static
    {
        return $this->state(fn (array $attributes) => [
            'tentatives_echouees' => config('plateforme.connexion.echecs_avant_verrouillage'),
            'verrouille_le' => now(),
        ]);
    }

    /**
     * Compte désactivé par un administrateur.
     */
    public function inactif(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutUtilisateur::Inactif,
        ]);
    }

    /**
     * Opérateur rattaché à un partenaire.
     */
    public function pourPartenaire(?Partenaire $partenaire = null): static
    {
        return $this->state(fn (array $attributes) => [
            'partenaire_id' => $partenaire?->id ?? Partenaire::factory(),
        ]);
    }
}
