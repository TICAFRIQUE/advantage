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
        Schema::create('partenaires', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('secteur')->nullable();
            $table->string('localisation')->nullable();
            $table->string('contact')->nullable();
            $table->decimal('taux_reduction', 5, 2);
            $table->string('statut', 20)->default('actif')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('partenaire_id')->nullable()->after('telephone')
                ->constrained('partenaires')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partenaire_id');
        });

        Schema::dropIfExists('partenaires');
    }
};
