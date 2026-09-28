<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rétention du journal d'audit (décision métier : purge après 14 jours
     * + suppression manuelle possible).
     *
     * - `purges_journal_audit` : registre de chaque purge, jamais effaçable
     *   (triggers UPDATE/DELETE bloquants) — on sait toujours qui a supprimé quoi.
     * - `journaux_audit` : la modification reste interdite ; la suppression
     *   n'est plus autorisée que si la session MySQL a levé le verrou
     *   « autoriser_purge_journal » (fait uniquement par l'action de purge).
     */
    public function up(): void
    {
        Schema::create('purges_journal_audit', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedBigInteger('purge_par_id')->nullable()->index();
            $table->timestamp('supprime_avant');
            $table->unsignedInteger('nombre_entrees');
            $table->string('motif')->nullable();
            $table->timestamp('cree_le')->useCurrent()->index();
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER purges_journal_audit_interdire_update BEFORE UPDATE ON purges_journal_audit
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'purges_journal_audit est en ajout seul'
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER purges_journal_audit_interdire_delete BEFORE DELETE ON purges_journal_audit
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'purges_journal_audit est en ajout seul'
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS journaux_audit_interdire_delete');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER journaux_audit_interdire_delete BEFORE DELETE ON journaux_audit
            FOR EACH ROW
            BEGIN
                IF @autoriser_purge_journal IS NULL OR @autoriser_purge_journal <> 1 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journaux_audit est en ajout seul';
                END IF;
            END
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS journaux_audit_interdire_delete');

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER journaux_audit_interdire_delete BEFORE DELETE ON journaux_audit
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journaux_audit est en ajout seul'
                SQL);
        }

        Schema::dropIfExists('purges_journal_audit');
    }
};
