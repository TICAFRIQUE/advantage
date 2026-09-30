<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Services\GenerateurPin;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Crée le compte super administrateur à partir de la configuration (.env),
 * avec un PIN à 5 chiffres comme tous les comptes : SUPERADMIN_PIN, ou à
 * défaut un PIN généré, affiché une seule fois.
 *
 * Idempotent : si le compte existe déjà, son PIN n'est jamais écrasé (il a pu
 * être réinitialisé depuis) ; seul le rôle est garanti.
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
            $pin = filled($config['pin']) ? $this->verifierPin((string) $config['pin']) : GenerateurPin::generer();

            $superadmin = User::create([
                'nom' => $config['nom'],
                'nom_utilisateur' => $config['nom_utilisateur'],
                'email' => $config['email'],
                'password' => $pin,
            ]);

            $this->command?->info("Compte superadmin « {$superadmin->nom_utilisateur} » créé.");

            if (blank($config['pin'])) {
                $this->command?->info("PIN généré : {$pin}");
                $this->command?->warn('Notez-le : il ne sera plus affiché.');
            }
        }

        // Compte de secours : il est toujours restauré s'il avait été supprimé.
        if ($superadmin->trashed()) {
            $superadmin->restore();
        }

        if (! $superadmin->hasRole(Role::Superadmin)) {
            $superadmin->assignRole(Role::Superadmin);
        }
    }

    private function verifierPin(string $pin): string
    {
        $longueur = (int) config('plateforme.connexion.longueur_pin', 5);

        if (preg_match('/^\d{'.$longueur.'}$/', $pin) !== 1) {
            throw new RuntimeException("SUPERADMIN_PIN doit contenir exactement {$longueur} chiffres.");
        }

        if (GenerateurPin::estTrivial($pin)) {
            throw new RuntimeException('SUPERADMIN_PIN est trop simple (chiffre répété ou suite comme 12345) : choisissez-en un autre.');
        }

        return $pin;
    }
}
