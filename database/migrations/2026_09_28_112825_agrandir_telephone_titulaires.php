<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Téléphones multi-pays : format E.164 complet (« + » et jusqu'à 15 chiffres).
     */
    public function up(): void
    {
        Schema::table('titulaires', function (Blueprint $table) {
            $table->string('telephone', 16)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('titulaires', function (Blueprint $table) {
            $table->string('telephone', 14)->change();
        });
    }
};
