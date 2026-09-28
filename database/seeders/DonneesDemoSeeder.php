<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Carte;
use App\Models\Partenaire;
use App\Models\Titulaire;
use App\Models\User;
use App\Services\GenerateurPin;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour l'environnement local uniquement.
 * Les PIN des comptes de démo sont affichés une seule fois dans la console.
 */
class DonneesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $partenaires = Partenaire::factory(6)->create();

        $comptes = [
            ['admin.demo', Role::Admin, null],
            ['agent.demo', Role::Agent, null],
            ['partenaire.demo', Role::Partenaire, $partenaires->first()],
        ];

        $lignes = [];

        foreach ($comptes as [$nomUtilisateur, $role, $partenaire]) {
            $pin = GenerateurPin::generer();

            User::factory()->create([
                'nom' => $role->libelle().' démo',
                'nom_utilisateur' => $nomUtilisateur,
                'password' => $pin,
                'partenaire_id' => $partenaire?->id,
            ])->assignRole($role);

            $lignes[] = [$nomUtilisateur, $role->libelle(), $pin];
        }

        $agent = User::query()->where('nom_utilisateur', 'agent.demo')->first();

        Titulaire::factory(20)->create()->each(function (Titulaire $titulaire) use ($agent): void {
            Carte::factory()
                ->for($titulaire)
                ->for($agent, 'activePar')
                ->activeeIlYa(fake()->numberBetween(0, 11), fake()->numberBetween(0, 28))
                ->create();
        });

        $this->command?->table(['Nom d\'utilisateur', 'Rôle', 'PIN'], $lignes);
    }
}
