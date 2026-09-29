<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité des comptes : qui a créé le compte et qui a modifié en dernier
 * sa fiche (nom, identifiant, contact, rôle). Le journal d'audit n'étant
 * conservé que 14 jours, l'information vit sur le compte lui-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cree_par_id')->nullable()->after('derniere_connexion_le')->constrained('users')->nullOnDelete();
            $table->foreignId('modifie_par_id')->nullable()->after('cree_par_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modifie_par_id');
            $table->dropConstrainedForeignId('cree_par_id');
        });
    }
};
