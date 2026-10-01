<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| En production, une seule entrée cron suffit :
| * * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Rétention du journal d'audit (14 jours par défaut), inscrite au registre des purges.
Schedule::command('journal:purger')->dailyAt('02:00')->withoutOverlapping()->onOneServer();

// Cartes échues : statut « expirée » aligné en base (historique : opération système).
Schedule::command('cartes:marquer-expirees')->dailyAt('00:10')->withoutOverlapping()->onOneServer();

// Alertes SMS d'expiration (3, 2, 1 mois), en journée uniquement.
Schedule::command('cartes:alertes-expiration')
    ->dailyAt((string) config('plateforme.alertes_expiration.heure', '09:00'))
    ->withoutOverlapping()
    ->onOneServer();

// Rétention des codes de validation et des SMS (plateforme.retention).
Schedule::command('donnees:purger')->dailyAt('02:30')->withoutOverlapping()->onOneServer();

// Historique des SMS : chaque lundi, seuls les plus récents sont conservés (50 par défaut).
Schedule::command('sms:purger-historique')->weeklyOn(1, '03:00')->withoutOverlapping()->onOneServer();

// Sauvegarde nocturne de la base (10 dernières conservées), si activée.
if (config('plateforme.sauvegardes.automatique')) {
    Schedule::command('sauvegarde:creer')->dailyAt('01:30')->withoutOverlapping()->onOneServer();
}
