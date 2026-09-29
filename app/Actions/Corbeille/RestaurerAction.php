<?php

namespace App\Actions\Corbeille;

use App\Enums\Role;
use App\Exceptions\RestaurationException;
use App\Models\Partenaire;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Restauration des éléments supprimés (archivés), réservée au superadmin.
 * La restauration est journalisée par les observers (*.restaure) ; l'auteur
 * de la suppression est effacé.
 */
class RestaurerAction
{
    /**
     * Restaure un partenaire et, sur demande, ses utilisateurs supprimés.
     * Un partenaire restauré garde son statut (actif ou inactif).
     *
     * @return int nombre d'utilisateurs restaurés avec lui
     *
     * @throws RestaurationException
     */
    public function partenaire(Partenaire $partenaire, User $auteur, bool $avecUtilisateurs): int
    {
        $this->autoriser($auteur);

        return DB::transaction(function () use ($partenaire, $avecUtilisateurs): int {
            $partenaire = Partenaire::onlyTrashed()->lockForUpdate()->find($partenaire->id)
                ?? throw new RestaurationException('Ce partenaire n\'est pas supprimé.');

            $partenaire->supprime_par_id = null;
            $partenaire->restore();

            if (! $avecUtilisateurs) {
                return 0;
            }

            $comptes = User::onlyTrashed()->where('partenaire_id', $partenaire->id)->get();
            $comptes->each(fn (User $compte) => $this->restaurerCompte($compte));

            return $comptes->count();
        });
    }

    /**
     * @throws RestaurationException
     */
    public function compte(User $compte, User $auteur): void
    {
        $this->autoriser($auteur);

        DB::transaction(function () use ($compte): void {
            $compte = User::onlyTrashed()->lockForUpdate()->find($compte->id)
                ?? throw new RestaurationException('Ce compte n\'est pas supprimé.');

            if ($compte->partenaire_id !== null && Partenaire::onlyTrashed()->whereKey($compte->partenaire_id)->exists()) {
                throw new RestaurationException('Son partenaire est supprimé : restaurez d\'abord le partenaire.');
            }

            $this->restaurerCompte($compte);
        });
    }

    private function restaurerCompte(User $compte): void
    {
        $compte->forceFill(['supprime_par_id' => null]);
        $compte->restore();
    }

    private function autoriser(User $auteur): void
    {
        if (! $auteur->hasRole(Role::Superadmin)) {
            throw new RestaurationException('La restauration est réservée au superadmin.');
        }
    }
}
