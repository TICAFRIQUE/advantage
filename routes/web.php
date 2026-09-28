<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\AccueilEspaceController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Agent;
use App\Http\Controllers\Partenaire;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('accueil');

/*
|--------------------------------------------------------------------------
| Espaces authentifiés
|--------------------------------------------------------------------------
|
| Défense en profondeur : chaque espace exige un rôle ET une permission
| d'accès ; chaque route fonctionnelle ajoutera sa propre `permission:` et
| une policy sur la ressource. Le superadmin est admis partout (rôle listé +
| Gate::before). Un test d'architecture refuse toute route authentifiée
| sans middleware `permission:` (tests/Feature/Http/SecuriteRoutesTest.php).
|
*/

Route::middleware(['auth', 'compte.actif'])->group(function () {
    Route::get('/espace', AccueilEspaceController::class)->name('accueil-espace');

    Route::prefix('admin')->name('admin.')
        ->middleware(['role:'.Role::Superadmin->value.'|'.Role::Admin->value, 'permission:'.Permission::AccederEspaceAdmin->value])
        ->group(function () {
            Route::get('/', Admin\TableauDeBordController::class)->name('tableau-de-bord');

            // Outils de test : boîte des SMS simulés (jamais en production).
            if (Admin\SmsSimulesController::disponible()) {
                Route::middleware('permission:'.Permission::VoirSmsSimules->value)->group(function () {
                    Route::get('/sms-simules', [Admin\SmsSimulesController::class, 'index'])->name('sms-simules.index');
                    Route::post('/sms-simules', [Admin\SmsSimulesController::class, 'store'])
                        ->middleware('throttle:10,1')
                        ->name('sms-simules.store');
                });
            }
        });

    Route::prefix('agent')->name('agent.')
        ->middleware(['role:'.Role::Superadmin->value.'|'.Role::Admin->value.'|'.Role::Agent->value, 'permission:'.Permission::AccederEspaceAgent->value])
        ->group(function () {
            Route::get('/', Agent\TableauDeBordController::class)->name('tableau-de-bord');

            Route::get('/cartes', [Agent\CarteController::class, 'index'])
                ->middleware('permission:'.Permission::RechercherCarte->value)
                ->name('cartes.index');
            Route::get('/cartes/activer', [Agent\CarteController::class, 'create'])
                ->middleware('permission:'.Permission::ActiverCarte->value)
                ->name('cartes.create');
            Route::post('/cartes', [Agent\CarteController::class, 'store'])
                ->middleware(['permission:'.Permission::ActiverCarte->value, 'throttle:activation-carte'])
                ->name('cartes.store');
            Route::get('/cartes/{carte}', [Agent\CarteController::class, 'show'])
                ->middleware('permission:'.Permission::RechercherCarte->value)
                ->name('cartes.show');
            Route::post('/cartes/{carte}/perte', [Agent\CarteController::class, 'declarerPerte'])
                ->middleware(['permission:'.Permission::SignalerCartePerdue->value, 'throttle:activation-carte'])
                ->name('cartes.perte');
            Route::post('/titulaires/recherche', Agent\RechercheTitulaireController::class)
                ->middleware(['permission:'.Permission::RechercherCarte->value, 'throttle:recherche-titulaire'])
                ->name('titulaires.recherche');
        });

    Route::prefix('partenaire')->name('partenaire.')
        ->middleware(['role:'.Role::Superadmin->value.'|'.Role::Admin->value.'|'.Role::Partenaire->value, 'permission:'.Permission::AccederEspacePartenaire->value, 'partenaire.actif'])
        ->group(function () {
            Route::get('/', Partenaire\TableauDeBordController::class)->name('tableau-de-bord');
        });
});
