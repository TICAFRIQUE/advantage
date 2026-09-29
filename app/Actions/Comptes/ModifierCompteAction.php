<?php

namespace App\Actions\Comptes;

use App\Enums\Role;
use App\Exceptions\OperationCompteException;
use App\Models\RoleUtilisateur;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Modifie la fiche d'un compte (nom, identifiant, contact) et, pour un compte
 * du back-office, son rôle. Droits revérifiés ici (GardeDroits) ; l'ancien
 * identifiant redevient libre, l'historique restant lié au compte (acteur_id).
 * Journalisation : UserObserver (fiche) et JournaliserChangementRole (rôle).
 */
class ModifierCompteAction
{
    /**
     * @param  array{nom: string, nom_utilisateur: string, email: ?string, telephone: ?string}  $donnees
     *
     * @throws OperationCompteException
     */
    public function __invoke(User $compte, array $donnees, Role|RoleUtilisateur|string|null $role, User $auteur): User
    {
        $role = $role === null ? null : RoleUtilisateur::depuis($role);

        if (! GardeDroits::peutGererCompte($auteur, $compte)) {
            throw new OperationCompteException('Vous n\'avez pas le droit de modifier ce compte.');
        }

        $changerRole = $role !== null && ! $compte->hasRole($role);

        $attribuables = array_map(fn (RoleUtilisateur $r) => $r->name, GardeDroits::rolesGestionAttribuables($auteur, $compte));

        if ($changerRole && ($compte->hasRole(Role::Partenaire) || ! in_array($role->name, $attribuables, true))) {
            throw new OperationCompteException("Vous n'avez pas le droit de donner le rôle « {$role->libelle()} » à ce compte.");
        }

        try {
            return DB::transaction(function () use ($compte, $donnees, $role, $changerRole, $auteur): User {
                $compte->fill($donnees);

                if ($compte->isDirty() || $changerRole) {
                    $compte->forceFill(['modifie_par_id' => $auteur->id])->save();
                }

                if ($changerRole) {
                    $compte->syncRoles([$role]);
                }

                return $compte;
            });
        } catch (UniqueConstraintViolationException) {
            throw new OperationCompteException('Ce nom d\'utilisateur ou cette adresse e-mail est déjà utilisé.');
        }
    }
}
