<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Crée le compte super administrateur à partir de la configuration (.env).
 *
 * Idempotent : si le compte existe déjà, son mot de passe n'est jamais
 * écrasé (il a pu être changé depuis) ; seul le rôle est garanti.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('plateforme.superadmin');

        if (blank($config['nom_utilisateur'])) {
            throw new RuntimeException('SUPERADMIN_NOM_UTILISATEUR doit être renseigné.');
        }

        $superadmin = User::query()->withTrashed()
            ->where('nom_utilisateur', mb_strtolower(trim($config['nom_utilisateur'])))
            ->first();

        if ($superadmin === null) {
            $this->verifierMotDePasse((string) $config['mot_de_passe'], (int) $config['longueur_min_mot_de_passe']);

            $superadmin = User::create([
                'nom' => $config['nom'],
                'nom_utilisateur' => $config['nom_utilisateur'],
                'email' => $config['email'],
                'password' => $config['mot_de_passe'],
            ]);

            $this->command?->info("Compte superadmin « {$superadmin->nom_utilisateur} » créé.");
        }

        // Compte de secours : il est toujours restauré s'il avait été supprimé.
        if ($superadmin->trashed()) {
            $superadmin->restore();
        }

        if (! $superadmin->hasRole(Role::Superadmin)) {
            $superadmin->assignRole(Role::Superadmin);
        }
    }

    private function verifierMotDePasse(string $motDePasse, int $longueurMin): void
    {
        if (mb_strlen($motDePasse) < $longueurMin) {
            throw new RuntimeException("SUPERADMIN_MOT_DE_PASSE doit contenir au moins {$longueurMin} caractères.");
        }
    }
}
