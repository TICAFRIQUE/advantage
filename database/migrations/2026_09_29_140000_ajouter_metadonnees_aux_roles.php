<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles personnalisés : libellé affiché, espace (gestion / partenaire),
 * indicateur de rôle système et auteurs. Les rôles système sont décrits dans
 * config/permissions.php et leurs métadonnées recopiées ici par
 * permissions:synchroniser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('libelle', 100)->nullable()->after('name');
            $table->string('espace', 20)->default('gestion')->after('libelle')->index();
            $table->boolean('systeme')->default(false)->after('espace');
            $table->foreignId('cree_par_id')->nullable()->after('systeme')->constrained('users')->nullOnDelete();
            $table->foreignId('modifie_par_id')->nullable()->after('cree_par_id')->constrained('users')->nullOnDelete();
        });

        foreach (config('permissions.roles') as $nom => $definition) {
            DB::table('roles')->where('name', $nom)->update([
                'libelle' => $definition['libelle'],
                'espace' => $definition['espace'],
                'systeme' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modifie_par_id');
            $table->dropConstrainedForeignId('cree_par_id');
            $table->dropIndex(['espace']);
            $table->dropColumn(['libelle', 'espace', 'systeme']);
        });
    }
};
