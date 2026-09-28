<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Redirige chaque utilisateur vers l'espace de son rôle après connexion.
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        $request->session()->put('connecte_le', now()->getTimestamp());

        return redirect()->route('accueil-espace');
    }
}
