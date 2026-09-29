<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — index alignés sur les requêtes réelles :
 * - liste des cartes triée par date d'activation, « mes activations » ;
 * - dernières transactions d'une carte (fiche carte) ;
 * - purge nocturne des codes et SMS (statut + date) ;
 * - journal d'audit filtré par action et trié par date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartes', function (Blueprint $table) {
            $table->index('active_le', 'cartes_active_le_index');
            $table->index(['active_par_id', 'active_le'], 'cartes_active_par_active_le_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['carte_id', 'validee_le'], 'transactions_carte_validee_le_index');
        });

        Schema::table('demandes_otp', function (Blueprint $table) {
            $table->index(['statut', 'created_at'], 'demandes_otp_statut_created_at_index');
        });

        Schema::table('messages_sms', function (Blueprint $table) {
            $table->index(['statut', 'created_at'], 'messages_sms_statut_created_at_index');
        });

        Schema::table('journaux_audit', function (Blueprint $table) {
            $table->index(['action', 'cree_le'], 'journaux_audit_action_cree_le_index');
        });
    }

    public function down(): void
    {
        Schema::table('cartes', function (Blueprint $table) {
            $table->dropIndex('cartes_active_le_index');
            $table->dropIndex('cartes_active_par_active_le_index');
        });

        Schema::table('transactions', fn (Blueprint $table) => $table->dropIndex('transactions_carte_validee_le_index'));
        Schema::table('demandes_otp', fn (Blueprint $table) => $table->dropIndex('demandes_otp_statut_created_at_index'));
        Schema::table('messages_sms', fn (Blueprint $table) => $table->dropIndex('messages_sms_statut_created_at_index'));
        Schema::table('journaux_audit', fn (Blueprint $table) => $table->dropIndex('journaux_audit_action_cree_le_index'));
    }
};
