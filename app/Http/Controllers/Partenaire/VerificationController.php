<?php

namespace App\Http\Controllers\Partenaire;

use App\Enums\StatutPartenaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaire\VerifierCarteRequest;
use App\Models\Partenaire;
use App\Services\EspaceTransaction;
use App\Services\PartenaireCourant;
use App\Services\VerifierCarteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Étape 1 du parcours de transaction : vérifier la carte (réponse binaire,
 * aucune donnée du titulaire). Dans le back-office, la page commence par
 * le choix du partenaire pour le compte duquel on agit.
 */
class VerificationController extends Controller
{
    public function create(Request $request): View
    {
        $peutChoisir = PartenaireCourant::peutChoisir($request->user());

        return view('partenaire.verifier', [
            'partenaire' => PartenaireCourant::pour($request->user()),
            'peutChoisir' => $peutChoisir,
            'partenairesActifs' => $peutChoisir
                ? Partenaire::query()->where('statut', StatutPartenaire::Actif)->orderBy('nom')->get(['id', 'nom', 'localisation', 'taux_reduction'])
                : collect(),
            'verification' => session('verification'),
        ]);
    }

    public function store(VerifierCarteRequest $request, VerifierCarteService $service): RedirectResponse
    {
        $partenaire = PartenaireCourant::pour($request->user());
        $numero = $request->validated('numero_carte');
        $carte = $service->verifier($numero, $partenaire);

        return redirect(EspaceTransaction::route('verifier'))->with('verification', [
            'numero_carte' => $numero,
            'numero_formate' => trim(chunk_split($numero, 3, ' ')),
            'valide' => $carte !== null,
            'taux' => $carte !== null ? (string) $partenaire->taux_reduction : null,
        ]);
    }
}
