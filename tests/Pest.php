<?php

use App\Enums\Role;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Models\Partenaire;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Les tests Feature utilisent la base MySQL dédiée `advantage_test` afin de
| valider les contraintes réelles (unicité, clés étrangères, verrous).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Crée un utilisateur doté d'un rôle système (les rôles sont seedés au besoin).
 * Un utilisateur « partenaire » est rattaché à un partenaire actif.
 *
 * @param  array<string, mixed>  $attributs
 */
function utilisateurAvecRole(Role $role, array $attributs = []): User
{
    test()->seed(RolesEtPermissionsSeeder::class);

    $factory = User::factory();

    if ($role === Role::Partenaire && ! array_key_exists('partenaire_id', $attributs)) {
        $factory = $factory->pourPartenaire(Partenaire::factory()->create());
    }

    return $factory->create($attributs)->assignRole($role);
}

/**
 * Authentifie l'utilisateur avec une session ouverte « maintenant »
 * (requise par le middleware CompteActif).
 */
function connecter(User $user): TestCase
{
    return test()->actingAs($user)->withSession(['connecte_le' => now()->getTimestamp()]);
}

/**
 * Connecte l'utilisateur avec un PIN confirmé à l'instant (actions protégées
 * par password.confirm).
 */
function avecPinRecent(User $user): TestCase
{
    return connecter($user)->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->getTimestamp(),
    ]);
}

/**
 * Formulaire de connexion affiché depuis quelques secondes : passe le filtre
 * anti-robots de ConnexionRequest comme un humain.
 */
function formulaireConnexionAffiche(): TestCase
{
    return test()->withSession([ConnexionRequest::CLE_AFFICHAGE => now()->subSeconds(5)->getTimestamp()]);
}
