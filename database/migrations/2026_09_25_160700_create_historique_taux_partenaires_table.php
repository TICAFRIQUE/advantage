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
        Schema::create('historique_taux_partenaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partenaire_id')->constrained('partenaires')->restrictOnDelete();
            $table->decimal('ancien_taux', 5, 2)->nullable();
            $table->decimal('nouveau_taux', 5, 2);
            $table->foreignId('modifie_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('modifie_le')->useCurrent();

            $table->index(['partenaire_id', 'modifie_le']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_taux_partenaires');
    }
};
