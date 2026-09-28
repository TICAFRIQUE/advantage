<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table append-only : aucune mise à jour ni suppression autorisée, ni via
     * Eloquent (voir JournalAudit) ni en SQL direct (triggers MySQL).
     */
    public function up(): void
    {
        Schema::create('journaux_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acteur_id')->nullable()->index();
            $table->string('type_acteur', 30);
            $table->string('action', 60)->index();
            $table->string('type_entite', 60)->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->json('donnees')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->timestamp('cree_le')->useCurrent()->index();

            $table->index(['type_entite', 'entite_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER journaux_audit_interdire_update BEFORE UPDATE ON journaux_audit
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journaux_audit est en ajout seul'
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER journaux_audit_interdire_delete BEFORE DELETE ON journaux_audit
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journaux_audit est en ajout seul'
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journaux_audit');
    }
};
