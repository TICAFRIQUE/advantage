<?php

namespace Database\Factories;

use App\Enums\StatutDemandeOtp;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\Partenaire;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<DemandeOtp>
 */
class DemandeOtpFactory extends Factory
{
    /**
     * Code en clair utilisé par défaut dans les tests.
     */
    public const CODE = '123456';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carte_id' => Carte::factory(),
            'partenaire_id' => Partenaire::factory(),
            'code_hash' => Hash::make(self::CODE),
            'demandee_le' => now(),
            'expire_le' => now()->addMinutes((int) config('plateforme.otp.duree_minutes')),
            'statut' => StatutDemandeOtp::EnAttente,
        ];
    }

    public function expiree(): static
    {
        return $this->state(fn (array $attributes) => [
            'demandee_le' => now()->subMinutes(10),
            'expire_le' => now()->subMinutes(5),
        ]);
    }

    public function utilisee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutDemandeOtp::Utilisee,
        ]);
    }
}
