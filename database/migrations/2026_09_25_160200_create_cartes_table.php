<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La contrainte unique sur `numero_carte` (lignes soft-deletées comprises)
     * garantit qu'un numéro n'est activé qu'une seule fois, à vie.
     *
     * Traçabilité : `active_par_id` = agent créateur, `modifie_par_id` =
     * dernier utilisateur ayant modifié la carte (le détail est dans l'audit).
     */
    public function up(): void
    {
        Schema::create('cartes', function (Blueprint $table) {
            $table->id();
            $table->char('numero_carte', 7)->unique();
            $table->foreignId('titulaire_id')->constrained('titulaires')->restrictOnDelete();
            $table->foreignId('active_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('active_le')->nullable();
            $table->timestamp('expire_le')->nullable();
            $table->string('statut', 20)->default('non_activee');
            $table->string('motif_statut')->nullable();
            $table->foreignId('modifie_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('statut');
            $table->index('expire_le');
            $table->index(['statut', 'expire_le']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartes');
    }
};
