<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le téléphone (format +225XXXXXXXXXX) identifie le titulaire : il reçoit
     * les codes OTP et sert de clé lors d'un renouvellement.
     *
     * La pièce d'identité n'est pas collectée au MVP ; les colonnes sont
     * conservées (nullable) pour une évolution future. Si elle est renseignée,
     * elle est chiffrée et recherchable via l'empreinte HMAC.
     */
    public function up(): void
    {
        Schema::create('titulaires', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 150);
            $table->string('telephone', 14)->unique();
            $table->text('numero_piece_identite')->nullable();
            $table->char('numero_piece_identite_hash', 64)->nullable()->unique();
            $table->string('statut', 20)->default('actif')->index();
            $table->foreignId('cree_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('modifie_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titulaires');
    }
};
