<?php

namespace Database\Seeders;

use App\Services\Droits\SynchroniserPermissions;
use Illuminate\Database\Seeder;

/**
 * Délègue à la synchronisation additive (même comportement que
 * php artisan permissions:synchroniser) : relançable sans risque.
 */
class RolesEtPermissionsSeeder extends Seeder
{
    public function run(SynchroniserPermissions $synchroniser): void
    {
        $synchroniser();
    }
}
