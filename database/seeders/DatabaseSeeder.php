<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Les événements de modèle doivent rester actifs : ils calculent
     * `cartes.expire_le`, l'empreinte HMAC des pièces d'identité et
     * alimentent le journal d'audit.
     */
    public function run(): void
    {
        $this->call([
            RolesEtPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (app()->isLocal()) {
            $this->call(DonneesDemoSeeder::class);
        }
    }
}
