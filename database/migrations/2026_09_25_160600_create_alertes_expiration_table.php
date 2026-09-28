<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'unicité (carte, palier, canal) empêche tout envoi en double d'une alerte.
     */
    public function up(): void
    {
        Schema::create('alertes_expiration', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carte_id')->constrained('cartes')->restrictOnDelete();
            $table->string('palier', 10);
            $table->string('canal', 10);
            $table->timestamp('envoyee_le')->nullable();
            $table->string('statut_livraison', 20)->default('en_attente')->index();
            $table->timestamps();

            $table->unique(['carte_id', 'palier', 'canal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alertes_expiration');
    }
};
