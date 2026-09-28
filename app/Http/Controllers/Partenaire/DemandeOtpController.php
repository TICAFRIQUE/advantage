<?php

namespace App\Http\Controllers\Partenaire;

use App\Actions\Partenaire\DemanderOtpAction;
use App\Actions\Partenaire\ValiderOtpAction;
use App\Enums\StatutDemandeOtp;
use App\Exceptions\OperationPartenaireException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partenaire\ConfirmerOtpRequest;
use App\Http\Requests\Partenaire\VerifierCarteRequest;
use App\Models\DemandeOtp;
use App\Services\PartenaireCourant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Étapes 2 et 3 : envoi du code au titulaire puis saisie du code en caisse.
 */
class DemandeOtpController extends Controller
{
    public function store(VerifierCarteRequest $request, DemanderOtpAction $demander): RedirectResponse
    {
        try {
            $demande = $demander($request->validated('numero_carte'), PartenaireCourant::pour($request->user()), $request->user());
        } catch (OperationPartenaireException $exception) {
            return redirect()->route('partenaire.verifier')->with('erreur', $exception->getMessage());
        }

        return redirect()->route('partenaire.codes.show', $demande);
    }

    public function show(DemandeOtp $demande): View|RedirectResponse
    {
        Gate::authorize('valider', $demande);

        if ($demande->statut === StatutDemandeOtp::Utilisee) {
            return redirect()->route('partenaire.transactions.show', $demande->transaction);
        }

        $demande->load('carte');

        return view('partenaire.code', [
            'demande' => $demande,
            'utilisable' => $demande->statut === StatutDemandeOtp::EnAttente && ! $demande->estExpiree(),
            'renvoiPossibleDans' => $this->renvoiPossibleDans($demande),
        ]);
    }

    public function valider(ConfirmerOtpRequest $request, DemandeOtp $demande, ValiderOtpAction $valider): RedirectResponse
    {
        try {
            $transaction = $valider($demande, $request->validated('code'), PartenaireCourant::pour($request->user()), $request->user());
        } catch (OperationPartenaireException $exception) {
            return redirect()->route('partenaire.codes.show', $demande)->withErrors(['code' => $exception->getMessage()]);
        }

        return redirect()->route('partenaire.transactions.show', $transaction);
    }

    public function renvoyer(Request $request, DemandeOtp $demande, DemanderOtpAction $demander): RedirectResponse
    {
        Gate::authorize('valider', $demande);

        if ($this->renvoiPossibleDans($demande) > 0) {
            return redirect()->route('partenaire.codes.show', $demande)
                ->with('erreur', 'Patientez avant de demander un nouveau code.');
        }

        try {
            $nouvelle = $demander($demande->carte->numero_carte, PartenaireCourant::pour($request->user()), $request->user());
        } catch (OperationPartenaireException $exception) {
            return redirect()->route('partenaire.codes.show', $demande)->with('erreur', $exception->getMessage());
        }

        return redirect()->route('partenaire.codes.show', $nouvelle)->with('succes', 'Un nouveau code a été envoyé au client.');
    }

    /**
     * Secondes restantes avant de pouvoir renvoyer un code (0 si possible).
     */
    private function renvoiPossibleDans(DemandeOtp $demande): int
    {
        $delai = (int) config('plateforme.otp.renvoi_apres_secondes');

        return max(0, (int) ceil(now()->diffInSeconds($demande->demandee_le->copy()->addSeconds($delai), false)));
    }
}
