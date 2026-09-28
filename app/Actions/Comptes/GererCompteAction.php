<?php

namespace App\Actions\Comptes;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StatutDemandeOtp;
use App\Enums\StatutUtilisateur;
use App\Exceptions\OperationCompteException;
use App\Models\DemandeOtp;
use App\Models\User;
use App\Services\Droits\GardeDroits;
use App\Services\GenerateurPin;
use Illuminate\Support\Facades\DB;

/**
 * Opérations sur un compte existant. Chaque méthode revérifie les droits
 * (GardeDroits) : un compte n'est géré que par un rang supérieur, jamais
 * soi-même, jamais un superadmin. Auteur null = console (accès serveur).
 * La journalisation est assurée par UserObserver (sans le PIN).
 */
class GererCompteAction
{
    /**
     * Nouveau PIN (affiché une fois), compte déverrouillé, échecs remis à zéro.
     *
     * @throws OperationCompteException
     */
    public function reinitialiserPin(User $compte, ?User $auteur): string
    {
        $this->autoriser($compte, $auteur);

        if ($compte->hasRole(Role::Superadmin)) {
            throw new OperationCompteException('Le superadmin utilise un mot de passe fort, pas un PIN.');
        }

        $pin = GenerateurPin::generer();

        $compte->forceFill(['password' => $pin, 'tentatives_echouees' => 0, 'verrouille_le' => null])->save();

        return $pin;
    }

    /**
     * @throws OperationCompteException
     */
    public function verrouiller(User $compte, ?User $auteur): void
    {
        $this->autoriser($compte, $auteur);

        $compte->forceFill(['verrouille_le' => now()])->save();
    }

    /**
     * @throws OperationCompteException
     */
    public function deverrouiller(User $compte, ?User $auteur): void
    {
        $this->autoriser($compte, $auteur);

        $compte->forceFill(['verrouille_le' => null, 'tentatives_echouees' => 0])->save();
    }

    /**
     * Un compte désactivé est déconnecté à sa requête suivante (CompteActif).
     *
     * @throws OperationCompteException
     */
    public function changerStatut(User $compte, StatutUtilisateur $statut, ?User $auteur): void
    {
        $this->autoriser($compte, $auteur);

        $compte->forceFill(['statut' => $statut])->save();
    }

    /**
     * Archivage (suppression douce) : le compte ne peut plus se connecter (le
     * garde ne retrouve plus un compte archivé) et disparaît des listes, mais
     * reste lisible dans l'historique (activations, transactions, journal).
     * Son nom d'utilisateur reste réservé. Ses codes en attente sont expirés.
     *
     * @throws OperationCompteException
     */
    public function supprimer(User $compte, ?User $auteur): void
    {
        $this->autoriser($compte, $auteur);

        if ($auteur !== null && ! $auteur->can(Permission::SupprimerComptes->value)) {
            throw new OperationCompteException('Vous n\'avez pas le droit de supprimer ce compte.');
        }

        DB::transaction(fn () => $this->archiver($compte));
    }

    /**
     * Sans contrôle de droits : réservé aux appelants qui les ont déjà vérifiés
     * (suppression d'un partenaire et de ses utilisateurs).
     */
    public function archiver(User $compte): void
    {
        DemandeOtp::query()
            ->where('demandee_par_id', $compte->id)
            ->where('statut', StatutDemandeOtp::EnAttente)
            ->update(['statut' => StatutDemandeOtp::Expiree, 'updated_at' => now()]);

        $compte->delete();
    }

    private function autoriser(User $compte, ?User $auteur): void
    {
        if ($auteur !== null && ! GardeDroits::peutGererCompte($auteur, $compte)) {
            throw new OperationCompteException('Vous n\'avez pas le droit de gérer ce compte.');
        }
    }
}
