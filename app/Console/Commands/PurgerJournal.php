<?php

namespace App\Console\Commands;

use App\Actions\Audit\PurgerJournalAudit;
use App\Enums\TypePurge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sans option : purge automatique selon la rétention (planifiée chaque jour).
 * Avec --avant : purge manuelle jusqu'à la date donnée, motif obligatoire.
 */
#[Signature('journal:purger
    {--avant= : Date limite (AAAA-MM-JJ) pour une purge manuelle}
    {--motif= : Motif obligatoire d\'une purge manuelle}')]
#[Description('Purge les entrées anciennes du journal d\'audit (inscrite au registre des purges)')]
class PurgerJournal extends Command
{
    public function handle(PurgerJournalAudit $purger): int
    {
        try {
            if ($this->option('avant') === null) {
                $jours = (int) config('plateforme.journal_audit.retention_jours');
                $supprimees = $purger(now()->subDays($jours), TypePurge::Automatique);
                $this->components->info("Purge automatique : {$supprimees} entrée(s) de plus de {$jours} jours supprimée(s).");

                return self::SUCCESS;
            }

            $avant = Carbon::createFromFormat('Y-m-d', (string) $this->option('avant'))->startOfDay();
            $supprimees = $purger($avant, TypePurge::Manuelle, motif: $this->option('motif'));
            $this->components->info("Purge manuelle : {$supprimees} entrée(s) antérieure(s) au {$avant->format('d/m/Y')} supprimée(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
