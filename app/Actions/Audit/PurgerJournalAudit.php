<?php

namespace App\Actions\Audit;

use App\Enums\TypePurge;
use App\Models\JournalAudit;
use App\Models\PurgeJournalAudit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Seul chemin autorisé pour supprimer des entrées du journal d'audit.
 *
 * Lève le verrou MySQL @autoriser_purge_journal le temps de la suppression
 * (le trigger bloque toute autre suppression), puis inscrit l'opération dans
 * le registre des purges, qui n'est jamais effaçable.
 */
class PurgerJournalAudit
{
    /**
     * @return int nombre d'entrées supprimées
     */
    public function __invoke(CarbonInterface $avant, TypePurge $type, ?User $auteur = null, ?string $motif = null): int
    {
        if ($type === TypePurge::Manuelle && blank($motif)) {
            throw new InvalidArgumentException('Une purge manuelle exige un motif.');
        }

        if ($avant->isFuture()) {
            throw new InvalidArgumentException('La date limite de purge ne peut pas être dans le futur.');
        }

        return DB::transaction(function () use ($avant, $type, $auteur, $motif): int {
            DB::statement('SET @autoriser_purge_journal = 1');

            try {
                $supprimees = JournalAudit::query()->where('cree_le', '<', $avant)->toBase()->delete();
            } finally {
                DB::statement('SET @autoriser_purge_journal = NULL');
            }

            PurgeJournalAudit::create([
                'type' => $type,
                'purge_par_id' => $auteur?->id,
                'supprime_avant' => $avant,
                'nombre_entrees' => $supprimees,
                'motif' => $motif,
            ]);

            return $supprimees;
        });
    }
}
