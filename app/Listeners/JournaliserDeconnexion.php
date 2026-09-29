<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\JournaliserAudit;
use Illuminate\Auth\Events\Logout;

class JournaliserDeconnexion
{
    public function handle(Logout $event): void
    {
        if ($event->user instanceof User) {
            JournaliserAudit::enregistrer('deconnexion', $event->user, ['nom_utilisateur' => $event->user->nom_utilisateur], $event->user);
        }
    }
}
