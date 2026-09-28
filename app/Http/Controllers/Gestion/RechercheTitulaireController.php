<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\RechercherTitulaireRequest;
use App\Models\Carte;
use App\Models\Titulaire;
use App\Services\JournaliserAudit;
use Illuminate\Http\JsonResponse;

/**
 * Pré-remplissage du formulaire d'activation lors d'un renouvellement.
 * Requête POST : le téléphone n'apparaît pas dans l'URL ni les logs d'accès.
 */
class RechercheTitulaireController extends Controller
{
    public function __invoke(RechercherTitulaireRequest $request): JsonResponse
    {
        $titulaire = Titulaire::query()
            ->parTelephone($request->validated('telephone'), $request->validated('pays_telephone'))
            ->with(['cartes' => fn ($query) => $query->latest('active_le')])
            ->first();

        JournaliserAudit::enregistrer('titulaire.recherche', $titulaire, ['trouve' => $titulaire !== null]);

        if ($titulaire === null) {
            return response()->json(['existe' => false]);
        }

        $carteEnCirculation = $titulaire->cartes->first(fn (Carte $carte) => $carte->estEnCirculation());

        return response()->json([
            'existe' => true,
            'titulaire' => [
                'nom' => $titulaire->nom,
                'prenom' => $titulaire->prenom,
                'telephone' => $titulaire->telephoneFormate(),
            ],
            'carte_en_circulation' => $carteEnCirculation?->numeroFormate(),
            'cartes' => $titulaire->cartes->map(fn (Carte $carte) => [
                'numero' => $carte->numeroFormate(),
                'statut' => $carte->statutEffectif()->libelle(),
                'expire_le' => $carte->expire_le?->format('d/m/Y'),
            ])->values(),
        ]);
    }
}
