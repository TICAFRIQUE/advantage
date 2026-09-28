<?php

namespace App\Http\Controllers\Partenaire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaire\VerifierCarteRequest;
use App\Models\DemandeOtp;
use App\Services\PartenaireCourant;
use App\Services\VerifierCarteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Étape 1 du parcours caisse : vérifier la carte (réponse binaire, aucune
 * donnée du titulaire).
 */
class VerificationController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', DemandeOtp::class);

        return view('partenaire.verifier', [
            'partenaire' => PartenaireCourant::pour(auth()->user()),
            'verification' => session('verification'),
        ]);
    }

    public function store(VerifierCarteRequest $request, VerifierCarteService $service): RedirectResponse
    {
        $partenaire = PartenaireCourant::pour($request->user());
        $numero = $request->validated('numero_carte');
        $carte = $service->verifier($numero, $partenaire);

        return redirect()->route('partenaire.verifier')->with('verification', [
            'numero_carte' => $numero,
            'numero_formate' => trim(chunk_split($numero, 3, ' ')),
            'valide' => $carte !== null,
            'taux' => $carte !== null ? (string) $partenaire->taux_reduction : null,
        ]);
    }
}
