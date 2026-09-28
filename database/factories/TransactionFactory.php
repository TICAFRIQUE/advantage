<?php

namespace Database\Factories;

use App\Enums\StatutTransaction;
use App\Models\DemandeOtp;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * La carte et le partenaire sont repris de la demande OTP associée.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'demande_otp_id' => DemandeOtp::factory()->utilisee(),
            'carte_id' => fn (array $attributes) => DemandeOtp::find($attributes['demande_otp_id'])->carte_id,
            'partenaire_id' => fn (array $attributes) => DemandeOtp::find($attributes['demande_otp_id'])->partenaire_id,
            'taux_applique' => 10,
            'validee_le' => now(),
            'statut' => StatutTransaction::Validee,
        ];
    }
}
