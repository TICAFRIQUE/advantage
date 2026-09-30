<?php

namespace App\Actions\Comptes;

use App\Enums\Role;
use App\Enums\StatutUtilisateur;
use App\Exceptions\OperationCompteException;
use App\Models\Partenaire;
use App\Models\RoleUtilisateur;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use App\Services\GenerateurPin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Crée un compte (agent, admin, opérateur partenaire) avec un PIN généré.
 * Le PIN est renvoyé UNE fois pour affichage ; seul son hash est stocké.
 */
class CreerCompteAction
{
    /**
     * @param  array{nom: string, nom_utilisateur: string, telephone?: ?string, email?: ?string}  $donnees
     * @return array{compte: User, pin: string}
     *
     * @throws OperationCompteException
     */
    public function __invoke(array $donnees, Role|RoleUtilisateur|string $role, User $auteur, ?Partenaire $partenaire = null): array
    {
        $role = RoleUtilisateur::depuis($role);

        // Le superadmin est créé uniquement depuis le .env (SuperAdminSeeder).
        if ($role->systeme() === Role::Superadmin) {
            throw new OperationCompteException('Le compte superadmin se crée uniquement depuis la configuration du serveur.');
        }

        if (! GardeDroits::peutAttribuerRole($auteur, new User, $role)) {
            throw new OperationCompteException("Vous n'avez pas le droit de créer un compte « {$role->libelle()} ».");
        }

        if (($role->espace() === 'partenaire') !== ($partenaire !== null)) {
            throw new OperationCompteException('Un opérateur doit être rattaché à un partenaire, et seulement lui.');
        }

        $pin = GenerateurPin::generer();

        try {
            $compte = DB::transaction(function () use ($donnees, $role, $partenaire, $pin, $auteur): User {
                $compte = new User;
                $compte->fill($donnees + ['password' => $pin]);
                // Champs protégés de l'affectation de masse : renseignés explicitement.
                $compte->forceFill(['statut' => StatutUtilisateur::Actif, 'partenaire_id' => $partenaire?->id, 'cree_par_id' => $auteur->id])->save();
                $compte->assignRole($role);

                return $compte;
            });
        } catch (UniqueConstraintViolationException) {
            throw new OperationCompteException('Ce nom d\'utilisateur est déjà pris.');
        }

        return ['compte' => $compte, 'pin' => $pin];
    }
}
