<?php

use App\Enums\StatutCarte;
use App\Enums\TypeOperationCarte;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique permanent des opérations sur les cartes (base du rapport des
     * cartes). Le journal d'audit n'est conservé que 14 jours : il ne peut pas
     * servir d'historique.
     *
     * Reprise de l'existant : activations (depuis `cartes`) et changements de
     * statut encore présents dans le journal d'audit.
     */
    public function up(): void
    {
        Schema::create('operations_cartes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carte_id')->constrained('cartes')->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignId('effectuee_par_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif')->nullable();
            $table->timestamp('effectuee_le')->useCurrent();

            $table->index('effectuee_le');
            $table->index(['type', 'effectuee_le']);
            $table->index(['carte_id', 'effectuee_le']);
            $table->index(['effectuee_par_id', 'effectuee_le']);
        });

        DB::table('operations_cartes')->insertUsing(
            ['carte_id', 'type', 'effectuee_par_id', 'effectuee_le'],
            DB::table('cartes')
                ->whereNotNull('active_le')
                ->selectRaw('id, ?, active_par_id, active_le', [TypeOperationCarte::Activation->value]),
        );

        DB::table('journaux_audit')
            ->where('action', 'carte.statut_modifie')
            ->whereIn('entite_id', DB::table('cartes')->select('id'))
            ->orderBy('id')
            ->each(function (object $entree): void {
                $donnees = json_decode((string) $entree->donnees, true) ?: [];
                $apres = StatutCarte::tryFrom((string) ($donnees['apres']['statut'] ?? ''));
                $avant = StatutCarte::tryFrom((string) ($donnees['avant']['statut'] ?? ''));
                $type = $apres ? TypeOperationCarte::depuisChangementStatut($avant, $apres) : null;

                if ($type === null) {
                    return;
                }

                DB::table('operations_cartes')->insert([
                    'carte_id' => $entree->entite_id,
                    'type' => $type->value,
                    'effectuee_par_id' => $type === TypeOperationCarte::Expiration ? null : $entree->acteur_id,
                    'motif' => $donnees['apres']['motif_statut'] ?? null,
                    'effectuee_le' => $entree->cree_le,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_cartes');
    }
};
