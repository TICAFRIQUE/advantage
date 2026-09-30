<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La pièce d'identité n'est plus prévue sur la plateforme : le titulaire
     * est identifié par son téléphone. Colonnes et empreinte HMAC retirées.
     */
    public function up(): void
    {
        Schema::table('titulaires', function (Blueprint $table) {
            $table->dropUnique(['numero_piece_identite_hash']);
            $table->dropColumn(['numero_piece_identite', 'numero_piece_identite_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('titulaires', function (Blueprint $table) {
            $table->text('numero_piece_identite')->nullable()->after('telephone');
            $table->char('numero_piece_identite_hash', 64)->nullable()->unique()->after('numero_piece_identite');
        });
    }
};
