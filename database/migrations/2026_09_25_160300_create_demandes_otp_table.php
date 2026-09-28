<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demandes_otp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carte_id')->constrained('cartes')->restrictOnDelete();
            $table->foreignId('partenaire_id')->constrained('partenaires')->restrictOnDelete();
            $table->foreignId('demandee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code_hash');
            $table->timestamp('demandee_le');
            $table->timestamp('expire_le');
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->string('statut', 20)->default('en_attente');
            $table->timestamp('utilisee_le')->nullable();
            $table->timestamps();

            $table->index(['carte_id', 'statut']);
            $table->index('expire_le');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demandes_otp');
    }
};
