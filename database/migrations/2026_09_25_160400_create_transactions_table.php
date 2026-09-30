<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'unicité de `demande_otp_id` garantit l'idempotence de la validation :
     * une demande OTP ne peut jamais produire deux transactions.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carte_id')->constrained('cartes')->restrictOnDelete();
            $table->foreignId('partenaire_id')->constrained('partenaires')->restrictOnDelete();
            $table->foreignId('demande_otp_id')->unique()->constrained('demandes_otp')->restrictOnDelete();
            $table->foreignId('valide_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('taux_applique', 5, 2);
            // Défaut explicite : jamais de ON UPDATE implicite (explicit_defaults_for_timestamp=OFF).
            $table->timestamp('validee_le')->useCurrent();
            $table->string('statut', 20)->default('validee')->index();
            $table->timestamps();

            $table->index('validee_le');
            $table->index(['partenaire_id', 'validee_le']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
