<?php

namespace App\Actions\Auth;

use App\Enums\StatutUtilisateur;
use App\Models\User;
use App\Services\JournaliserAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Authentifie un utilisateur par nom d'utilisateur + PIN (5 chiffres, pour
 * tous les comptes), avec verrouillage après échecs cumulés.
 *
 * Règles anti-énumération :
 * - un compte inexistant et un PIN faux produisent le même message ;
 * - le temps de réponse est équivalent (hash factice si compte introuvable) ;
 * - l'état « verrouillé / désactivé » n'est révélé qu'à qui connaît le bon PIN.
 */
class AuthentifierUtilisateur
{
    public function __construct(private EnregistrerEchecAuthentification $enregistrerEchec) {}

    public function __invoke(Request $request): ?User
    {
        $nomUtilisateur = (string) $request->input(Fortify::username());
        $secret = (string) $request->input('password');

        $user = User::query()->where('nom_utilisateur', $nomUtilisateur)->first();

        if ($user === null) {
            Hash::check($secret, $this->hashFactice());

            // Un identifiant purement numérique est probablement un PIN saisi
            // dans le mauvais champ : il n'est jamais journalisé.
            JournaliserAudit::enregistrer('connexion.echec', donnees: [
                'nom_utilisateur' => ctype_digit($nomUtilisateur) ? '[masqué]' : $nomUtilisateur,
                'motif' => 'inconnu',
                'navigateur' => JournaliserAudit::navigateur(),
            ]);

            return null;
        }

        if (! Hash::check($secret, $user->password)) {
            ($this->enregistrerEchec)($user, 'connexion');

            return null;
        }

        if ($user->estVerrouille()) {
            JournaliserAudit::enregistrer('connexion.refusee', $user, ['nom_utilisateur' => $user->nom_utilisateur, 'motif' => 'verrouille', 'navigateur' => JournaliserAudit::navigateur()], $user);

            throw ValidationException::withMessages([
                Fortify::username() => __('Ce compte est verrouillé. Contactez un administrateur.'),
            ]);
        }

        if ($user->statut !== StatutUtilisateur::Actif) {
            JournaliserAudit::enregistrer('connexion.refusee', $user, ['nom_utilisateur' => $user->nom_utilisateur, 'motif' => 'inactif', 'navigateur' => JournaliserAudit::navigateur()], $user);

            throw ValidationException::withMessages([
                Fortify::username() => __('Ce compte est désactivé. Contactez un administrateur.'),
            ]);
        }

        User::query()->whereKey($user->id)->update([
            'tentatives_echouees' => 0,
            'derniere_connexion_le' => now(),
        ]);

        JournaliserAudit::enregistrer('connexion.reussie', $user, ['nom_utilisateur' => $user->nom_utilisateur, 'navigateur' => JournaliserAudit::navigateur()], $user);

        return $user;
    }

    /**
     * Hash bcrypt de référence pour égaliser le temps de réponse.
     */
    private function hashFactice(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('advantage-hash-factice');
    }
}
