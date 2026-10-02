<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suppression définitive d'une carte (cartes de test créées en production).
     *
     * - `suppressions_cartes` : registre de chaque suppression, jamais
     *   effaçable (triggers UPDATE/DELETE bloquants). Aucune donnée
     *   personnelle du titulaire : seulement le numéro, l'auteur, le motif et
     *   le nombre de lignes supprimées.
     * - `operations_cartes` : la modification reste interdite ; la suppression
     *   n'est autorisée que si la session MySQL a levé le verrou
     *   « autoriser_suppression_carte » (fait uniquement par
     *   SupprimerCarteDefinitivementAction).
     */
    public function up(): void
    {
        Schema::create('suppressions_cartes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_carte', 7)->index();
            $table->unsignedBigInteger('supprime_par_id')->nullable()->index();
            $table->string('motif', 200);
            $table->unsignedInteger('transactions_supprimees')->default(0);
            $table->unsignedInteger('codes_supprimes')->default(0);
            $table->unsignedInteger('alertes_supprimees')->default(0);
            $table->unsignedInteger('operations_supprimees')->default(0);
            $table->unsignedInteger('sms_supprimes')->default(0);
            $table->boolean('titulaire_supprime')->default(false);
            $table->timestamp('cree_le')->useCurrent()->index();
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER suppressions_cartes_interdire_update BEFORE UPDATE ON suppressions_cartes
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'suppressions_cartes est en ajout seul'
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER suppressions_cartes_interdire_delete BEFORE DELETE ON suppressions_cartes
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'suppressions_cartes est en ajout seul'
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS operations_cartes_interdire_delete');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER operations_cartes_interdire_delete BEFORE DELETE ON operations_cartes
            FOR EACH ROW
            BEGIN
                IF @autoriser_suppression_carte IS NULL OR @autoriser_suppression_carte <> 1 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operations_cartes est en ajout seul';
                END IF;
            END
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS operations_cartes_interdire_delete');

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER operations_cartes_interdire_delete BEFORE DELETE ON operations_cartes
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operations_cartes est en ajout seul'
                SQL);

            DB::unprepared('DROP TRIGGER IF EXISTS suppressions_cartes_interdire_update');
            DB::unprepared('DROP TRIGGER IF EXISTS suppressions_cartes_interdire_delete');
        }

        Schema::dropIfExists('suppressions_cartes');
    }
};
