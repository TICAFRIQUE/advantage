<?php

namespace App\Services\Listes;

use App\Enums\StatutUtilisateur;
use App\Http\Requests\Gestion\FiltrerUtilisateursRequest;
use App\Models\RoleUtilisateur;
use App\Models\User;
use App\Services\Telephone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres › Utilisateurs du back-office (filtres : rôle, état).
 *
 * @extends Liste<User>
 */
class ListeUtilisateurs extends Liste
{
    public function cle(): string
    {
        return 'utilisateurs';
    }

    public function titre(): string
    {
        return 'Utilisateurs du back-office';
    }

    public function requete(): Builder
    {
        $f = $this->filtres;

        return User::query()->select('users.*')
            ->whereHas('roles', fn ($q) => $q->where('espace', 'gestion'))
            ->with(['roles', 'creePar.roles'])
            ->when($f['role'] ?? null, fn ($q, string $role) => $q->role($role))
            ->when($f['etat'] ?? null, fn ($q, string $etat) => match ($etat) {
                'verrouille' => $q->whereNotNull('users.verrouille_le'),
                'inactif' => $q->where('users.statut', StatutUtilisateur::Inactif),
                default => $q->where('users.statut', StatutUtilisateur::Actif)->whereNull('users.verrouille_le'),
            });
    }

    public function rechercher(Builder $query, string $recherche): void
    {
        $texte = addcslashes($recherche, '%_\\');

        $query->where(fn ($q) => $q
            ->where('users.nom', 'like', "%{$texte}%")
            ->orWhere('users.nom_utilisateur', 'like', "%{$texte}%"));
    }

    protected function trier(Builder $query): Builder
    {
        return $query->orderBy('users.nom');
    }

    public function colonnes(): array
    {
        return ['Nom', 'Identifiant', 'Rôle', 'État', 'Téléphone', 'E-mail', 'Dernière connexion', 'Créé par'];
    }

    /**
     * @param  User  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->nom,
            $modele->nom_utilisateur,
            $modele->rolePrincipal()?->libelle(),
            self::etat($modele),
            $modele->telephone ? Telephone::formater($modele->telephone) : null,
            $modele->email,
            $modele->derniere_connexion_le?->format('d/m/Y H:i') ?? 'Jamais',
            $modele->creePar?->libelleActeur() ?? 'Système',
        ];
    }

    public function filtresLisibles(): array
    {
        return array_filter([
            'Rôle' => filled($this->filtres['role'] ?? null) ? RoleUtilisateur::query()->where('name', $this->filtres['role'])->first()?->libelle() : null,
            'État' => FiltrerUtilisateursRequest::ETATS[$this->filtres['etat'] ?? ''] ?? null,
        ]);
    }

    public static function etat(User $compte): string
    {
        return match (true) {
            $compte->estVerrouille() => 'Verrouillé',
            $compte->statut !== StatutUtilisateur::Actif => 'Désactivé',
            default => 'Actif',
        };
    }
}
