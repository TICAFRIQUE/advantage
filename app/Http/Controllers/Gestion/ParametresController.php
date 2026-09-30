<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\EnregistrerIdentiteRequest;
use App\Services\JournaliserAudit;
use App\Services\Parametres;
use App\Services\Parametres\EnregistrerLogo;
use App\Services\Sauvegardes\GestionSauvegardes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administration › Paramètres : identité de l'application (nom, logo) et,
 * pour le superadmin, sauvegardes de la base.
 */
class ParametresController extends Controller
{
    public function index(Request $request, GestionSauvegardes $sauvegardes): View
    {
        $user = $request->user();
        $peutSauvegarder = $user->hasRole(Role::Superadmin) && $user->can(Permission::GererSauvegardes->value);

        return view('gestion.parametres.index', [
            'peutIdentite' => $user->can(Permission::GererParametres->value),
            'peutSauvegarder' => $peutSauvegarder,
            'sauvegardes' => $peutSauvegarder ? $sauvegardes->lister() : [],
            'dossier' => $peutSauvegarder ? $sauvegardes->dossier() : null,
            'dossierParDefaut' => $peutSauvegarder ? GestionSauvegardes::dossierParDefaut() : null,
            'conserver' => (int) config('plateforme.sauvegardes.conserver', 10),
            'automatique' => (bool) config('plateforme.sauvegardes.automatique'),
            'onglet' => $request->query('onglet') === 'sauvegardes' && $peutSauvegarder ? 'sauvegardes' : ($user->can(Permission::GererParametres->value) ? 'identite' : 'sauvegardes'),
        ]);
    }

    public function identite(EnregistrerIdentiteRequest $request, EnregistrerLogo $logo): RedirectResponse
    {
        $avant = ['nom_application' => Parametres::nomApplication(), 'nom_organisation' => Parametres::nomOrganisation()];

        Parametres::definir('identite.nom_application', $request->validated('nom_application'), $request->user());
        Parametres::definir('identite.nom_organisation', $request->validated('nom_organisation'), $request->user());

        if ($request->hasFile('logo')) {
            $logo->enregistrer($request->file('logo'), $request->user());
        } elseif ($request->boolean('logo_defaut')) {
            $logo->reinitialiser($request->user());
        }

        JournaliserAudit::enregistrer('parametres.modifies', donnees: [
            'avant' => $avant,
            'apres' => [
                'nom_application' => $request->validated('nom_application'),
                'nom_organisation' => $request->validated('nom_organisation'),
                'logo' => $request->hasFile('logo') ? 'nouveau logo' : ($request->boolean('logo_defaut') ? 'logo par défaut' : 'inchangé'),
            ],
        ]);

        return redirect()->route('gestion.parametres.index')->with('succes', 'Paramètres enregistrés.');
    }
}
