<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auteur de la suppression (archivage) d'un partenaire ou d'un compte :
 * affiché dans la page « Éléments supprimés », remis à null à la restauration.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['partenaires', 'users'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('supprime_par_id')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['partenaires', 'users'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('supprime_par_id');
            });
        }
    }
};
