<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Confirmation du PIN avant une action sensible. Chaque échec compte dans
 * le verrouillage du compte : une session volée ne permet pas de tester
 * les 100 000 PIN possibles (la route Fortify n'est pas limitée en débit).
 */
class ConfirmerMotDePasse
{
    public function __construct(private EnregistrerEchecAuthentification $enregistrerEchec) {}

    public function __invoke(User $user, ?string $secret): bool
    {
        if (Hash::check((string) $secret, $user->password)) {
            return true;
        }

        ($this->enregistrerEchec)($user, 'confirmation');

        return false;
    }
}
