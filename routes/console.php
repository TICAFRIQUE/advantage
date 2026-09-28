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
