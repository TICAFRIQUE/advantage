<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Aiguille l'utilisateur connecté vers l'espace correspondant à son rôle.
 * Un compte sans rôle reconnu est déconnecté.
 */
class AccueilEspaceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $role = $request->user()->rolePrincipal();

        if ($role === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['nom_utilisateur' => __('Aucun espace n\'est associé à ce compte. Contactez un administrateur.')]);
        }

        return redirect()->route($role->routeAccueil());
    }
}
