<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Historique permanent des cartes en ajout seul, garanti par la base (comme
 * journaux_audit) : aucune modification ni suppression, même en SQL direct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER operations_cartes_interdire_update BEFORE UPDATE ON operations_cartes
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operations_cartes est en ajout seul'
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER operations_cartes_interdire_delete BEFORE DELETE ON operations_cartes
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operations_cartes est en ajout seul'
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS operations_cartes_interdire_update');
        DB::unprepared('DROP TRIGGER IF EXISTS operations_cartes_interdire_delete');
    }
};
