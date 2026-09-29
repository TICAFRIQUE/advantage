<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Services\JournaliserAudit;
use App\Services\Parametres;
use App\Services\Sauvegardes\GestionSauvegardes;
use App\Services\Sauvegardes\SauvegardeException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sauvegardes de la base (superadmin, routes role:superadmin +
 * permission gerer-sauvegardes) : créer, télécharger, restaurer, supprimer,
 * choisir le dossier. Chaque action est journalisée.
 */
class SauvegardeController extends Controller
{
    public function store(Request $request, GestionSauvegardes $sauvegardes): RedirectResponse
    {
        return $this->executer(fn () => "Sauvegarde créée : {$sauvegardes->creer($request->user())}.");
    }

    public function telecharger(Request $request, string $nom, GestionSauvegardes $sauvegardes): BinaryFileResponse|RedirectResponse
    {
        try {
            $chemin = $sauvegardes->chemin($nom);
        } catch (SauvegardeException $exception) {
            return $this->retour()->with('erreur', $exception->getMessage());
        }

        JournaliserAudit::enregistrer('sauvegarde.telechargee', donnees: ['fichier' => $nom]);

        return response()->download($chemin, $nom, ['Content-Type' => 'application/gzip']);
    }

    public function restaurer(Request $request, string $nom, GestionSauvegardes $sauvegardes): RedirectResponse
    {
        $request->validate(
            ['confirmation' => ['required', 'string', 'in:RESTAURER']],
            ['confirmation.in' => 'Saisissez RESTAURER en majuscules pour confirmer.', 'confirmation.required' => 'Saisissez RESTAURER en majuscules pour confirmer.'],
        );

        return $this->executer(function () use ($request, $nom, $sauvegardes): string {
            $securite = $sauvegardes->restaurer($nom, $request->user());

            return "Base restaurée depuis {$nom}. L'état précédent a été sauvegardé dans {$securite}.";
        });
    }

    public function supprimer(Request $request, string $nom, GestionSauvegardes $sauvegardes): RedirectResponse
    {
        return $this->executer(function () use ($request, $nom, $sauvegardes): string {
            $sauvegardes->supprimer($nom, $request->user());

            return "Sauvegarde {$nom} supprimée.";
        });
    }

    public function dossier(Request $request): RedirectResponse
    {
        $request->validate(['dossier' => ['required', 'string', 'max:255']]);

        return $this->executer(function () use ($request): string {
            $dossier = GestionSauvegardes::verifierDossier($request->input('dossier'));
            Parametres::definir('sauvegardes.dossier', $dossier, $request->user());
            JournaliserAudit::enregistrer('parametres.modifies', donnees: ['apres' => ['dossier_sauvegardes' => $dossier]]);

            return "Dossier des sauvegardes : {$dossier}.";
        });
    }

    /**
     * @param  callable(): string  $operation
     */
    private function executer(callable $operation): RedirectResponse
    {
        try {
            return $this->retour()->with('succes', $operation());
        } catch (SauvegardeException $exception) {
            return $this->retour()->withInput()->with('erreur', $exception->getMessage());
        }
    }

    private function retour(): RedirectResponse
    {
        return redirect()->route('gestion.parametres.index', ['onglet' => 'sauvegardes']);
    }
}
