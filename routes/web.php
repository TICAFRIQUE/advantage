<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\AccueilEspaceController;
use App\Http\Controllers\Gestion;
use App\Http\Controllers\Partenaire;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('accueil');

/*
|--------------------------------------------------------------------------
| Espaces authentifiés
|--------------------------------------------------------------------------
|
| - /gestion : back-office unique (superadmin, admin, agent) ; ce que chacun
|   voit et fait dépend uniquement de ses permissions.
| - /partenaire : espace à part, réservé aux opérateurs partenaires.
|
| Défense en profondeur : rôle + permission d'accès sur chaque espace, puis
| permission propre et policy sur chaque route. Un test d'architecture
| refuse toute route authentifiée sans middleware `permission:`.
|
*/

/**
 * Parcours de transaction (vérification → code → résultat), partagé par les
 * deux espaces avec les mêmes contrôleurs ; seules la permission et le
 * préfixe des routes changent.
 */
$parcoursTransaction = function (string $permission): void {
    Route::get('/', [Partenaire\VerificationController::class, 'create'])
        ->middleware('permission:'.$permission)
        ->name('verifier');

    Route::middleware('partenaire.courant')->group(function () use ($permission) {
        Route::post('/verifier', [Partenaire\VerificationController::class, 'store'])
            ->middleware(['permission:'.$permission, 'throttle:verification-carte'])
            ->name('verifier.store');
        Route::post('/codes', [Partenaire\DemandeOtpController::class, 'store'])
            ->middleware(['permission:'.$permission, 'throttle:verification-carte'])
            ->name('codes.store');
        Route::get('/codes/{demande}', [Partenaire\DemandeOtpController::class, 'show'])
            ->middleware('permission:'.$permission)
            ->name('codes.show');
        Route::post('/codes/{demande}/valider', [Partenaire\DemandeOtpController::class, 'valider'])
            ->middleware(['permission:'.$permission, 'throttle:confirmation-otp'])
            ->name('codes.valider');
        Route::post('/codes/{demande}/renvoyer', [Partenaire\DemandeOtpController::class, 'renvoyer'])
            ->middleware(['permission:'.$permission, 'throttle:verification-carte'])
            ->name('codes.renvoyer');
        Route::get('/resultat/{transaction}', [Partenaire\TransactionController::class, 'show'])
            ->middleware('permission:'.$permission)
            ->name('resultat');
    });
};

Route::middleware(['auth', 'compte.actif'])->group(function () use ($parcoursTransaction) {
    Route::get('/espace', AccueilEspaceController::class)->name('accueil-espace');

    /*
    |----------------------------------------------------------------------
    | Back-office
    |----------------------------------------------------------------------
    */
    Route::prefix('gestion')->name('gestion.')
        ->middleware([
            'role:'.implode('|', array_map(fn (Role $role) => $role->value, Role::roleGestion())),
            'permission:'.Permission::AccederGestion->value,
        ])
        ->group(function () use ($parcoursTransaction) {
            Route::get('/', Gestion\TableauDeBordController::class)
                ->middleware('permission:'.Permission::VoirTableauDeBord->value)
                ->name('tableau-de-bord');

            // Cartes
            Route::get('/cartes', [Gestion\CarteController::class, 'index'])
                ->middleware('permission:'.Permission::VoirCartes->value)
                ->name('cartes.index');
            Route::get('/cartes/activer', [Gestion\CarteController::class, 'create'])
                ->middleware('permission:'.Permission::ActiverCarte->value)
                ->name('cartes.create');
            Route::post('/cartes', [Gestion\CarteController::class, 'store'])
                ->middleware(['permission:'.Permission::ActiverCarte->value, 'throttle:activation-carte'])
                ->name('cartes.store');
            Route::get('/cartes/rapport', [Gestion\RapportCartesController::class, 'index'])
                ->middleware('permission:'.Permission::VoirRapportCartes->value)
                ->name('cartes.rapport');
            Route::get('/cartes/rapport/donnees', [Gestion\RapportCartesController::class, 'donnees'])
                ->middleware('permission:'.Permission::VoirRapportCartes->value)
                ->name('cartes.rapport.donnees');
            Route::get('/cartes/{carte}', [Gestion\CarteController::class, 'show'])
                ->whereNumber('carte')
                ->middleware('permission:'.Permission::VoirCartes->value)
                ->name('cartes.show');
            Route::post('/cartes/{carte}/statut', Gestion\StatutCarteController::class)
                ->middleware(['permission:'.Permission::GererStatutCarte->value, 'throttle:activation-carte'])
                ->name('cartes.statut');

            // Titulaire : identité (permission dédiée) et téléphone (permission
            // dédiée + PIN confirmé depuis moins de 5 minutes).
            Route::get('/cartes/{carte}/titulaire', [Gestion\TitulaireController::class, 'edit'])
                ->middleware('permission:'.Permission::ModifierTitulaire->value.'|'.Permission::ModifierTelephoneTitulaire->value)
                ->name('cartes.titulaire.edit');
            Route::put('/cartes/{carte}/titulaire', [Gestion\TitulaireController::class, 'update'])
                ->middleware('permission:'.Permission::ModifierTitulaire->value)
                ->name('cartes.titulaire.update');
            Route::middleware(['permission:'.Permission::ModifierTelephoneTitulaire->value, 'password.confirm:password.confirm,300'])
                ->group(function () {
                    Route::get('/cartes/{carte}/titulaire/telephone', [Gestion\TitulaireController::class, 'editTelephone'])
                        ->name('cartes.titulaire.telephone.edit');
                    Route::put('/cartes/{carte}/titulaire/telephone', [Gestion\TitulaireController::class, 'updateTelephone'])
                        ->name('cartes.titulaire.telephone.update');
                });
            Route::post('/titulaires/recherche', Gestion\RechercheTitulaireController::class)
                ->middleware(['permission:'.Permission::ActiverCarte->value, 'throttle:recherche-titulaire'])
                ->name('titulaires.recherche');

            // Partenaires
            Route::get('/partenaires', [Gestion\PartenaireController::class, 'index'])
                ->middleware('permission:'.Permission::VoirPartenaires->value)
                ->name('partenaires.index');
            Route::get('/partenaires/donnees', [Gestion\PartenaireController::class, 'donnees'])
                ->middleware('permission:'.Permission::VoirPartenaires->value)
                ->name('partenaires.donnees');
            Route::get('/partenaires/creer', [Gestion\PartenaireController::class, 'create'])
                ->middleware('permission:'.Permission::GererPartenaires->value)
                ->name('partenaires.create');
            Route::post('/partenaires', [Gestion\PartenaireController::class, 'store'])
                ->middleware('permission:'.Permission::GererPartenaires->value)
                ->name('partenaires.store');
            Route::get('/partenaires/{partenaire}', [Gestion\PartenaireController::class, 'show'])
                ->whereNumber('partenaire')
                ->middleware('permission:'.Permission::VoirPartenaires->value)
                ->name('partenaires.show');
            Route::get('/partenaires/{partenaire}/modifier', [Gestion\PartenaireController::class, 'edit'])
                ->middleware('permission:'.Permission::GererPartenaires->value)
                ->name('partenaires.edit');
            Route::put('/partenaires/{partenaire}', [Gestion\PartenaireController::class, 'update'])
                ->middleware('permission:'.Permission::GererPartenaires->value)
                ->name('partenaires.update');
            Route::post('/partenaires/{partenaire}/statut', [Gestion\PartenaireController::class, 'changerStatut'])
                ->middleware('permission:'.Permission::GererPartenaires->value)
                ->name('partenaires.statut');
            Route::delete('/partenaires/{partenaire}', [Gestion\PartenaireController::class, 'destroy'])
                ->whereNumber('partenaire')
                ->middleware(['permission:'.Permission::SupprimerPartenaires->value, 'password.confirm:password.confirm,300'])
                ->name('partenaires.destroy');
            Route::post('/partenaires/{partenaire}/operateurs', [Gestion\OperateurController::class, 'store'])
                ->middleware(['permission:'.Permission::GererOperateursPartenaires->value, 'throttle:activation-carte'])
                ->name('partenaires.operateurs.store');

            // Comptes (opérateurs ici, agents et admins à l'étape Paramètres) :
            // la policy UserPolicy::gerer applique les règles anti-élévation.
            Route::prefix('comptes/{compte}')->name('comptes.')->whereNumber('compte')
                ->middleware('permission:'.Permission::GererUtilisateurs->value.'|'.Permission::GererOperateursPartenaires->value)
                ->group(function () {
                    Route::post('/pin', [Gestion\CompteController::class, 'reinitialiserPin'])
                        ->middleware(['password.confirm:password.confirm,300', 'throttle:activation-carte'])
                        ->name('pin');
                    Route::post('/verrouillage', [Gestion\CompteController::class, 'verrouillage'])->name('verrouillage');
                    Route::post('/statut', [Gestion\CompteController::class, 'statut'])->name('statut');
                    Route::delete('/', [Gestion\CompteController::class, 'supprimer'])
                        ->middleware(['permission:'.Permission::SupprimerComptes->value, 'password.confirm:password.confirm,300'])
                        ->name('supprimer');
                });

            Route::get('/transactions/rapport', [Gestion\RapportTransactionsController::class, 'index'])
                ->middleware('permission:'.Permission::VoirRapportTransactions->value)
                ->name('transactions.rapport');
            Route::get('/transactions/rapport/donnees', [Gestion\RapportTransactionsController::class, 'donnees'])
                ->middleware('permission:'.Permission::VoirRapportTransactions->value)
                ->name('transactions.rapport.donnees');

            // Partenaires : transaction pour le compte d'un partenaire (option A).
            Route::prefix('transaction')->name('transaction.')->group(function () use ($parcoursTransaction) {
                Route::get('/nouvelle', [Partenaire\PartenaireCourantController::class, 'nouvelle'])
                    ->middleware('permission:'.Permission::EffectuerTransactionPartenaire->value)
                    ->name('nouvelle');
                Route::post('/partenaire-courant', [Partenaire\PartenaireCourantController::class, 'store'])
                    ->middleware('permission:'.Permission::EffectuerTransactionPartenaire->value)
                    ->name('partenaire-courant.store');
                $parcoursTransaction(Permission::EffectuerTransactionPartenaire->value);
            });

            // Outils de test : boîte des SMS simulés (jamais en production).
            if (Gestion\SmsSimulesController::disponible()) {
                Route::middleware('permission:'.Permission::VoirSmsSimules->value)->group(function () {
                    Route::get('/outils/sms-simules', [Gestion\SmsSimulesController::class, 'index'])->name('sms-simules.index');
                    Route::post('/outils/sms-simules', [Gestion\SmsSimulesController::class, 'store'])
                        ->middleware('throttle:10,1')
                        ->name('sms-simules.store');
                });
            }
        });

    /*
    |----------------------------------------------------------------------
    | Espace partenaire (rôle partenaire uniquement)
    |----------------------------------------------------------------------
    */
    Route::prefix('partenaire')->name('partenaire.')
        ->middleware([
            'role:'.Role::Partenaire->value,
            'permission:'.Permission::AccederEspacePartenaire->value,
            'partenaire.actif',
        ])
        ->group(function () use ($parcoursTransaction) {
            Route::get('/', Partenaire\TableauDeBordController::class)->name('tableau-de-bord');

            Route::prefix('transaction')->name('transaction.')->group(
                fn () => $parcoursTransaction(Permission::EffectuerTransaction->value)
            );

            Route::get('/historique', [Partenaire\TransactionController::class, 'index'])
                ->middleware('permission:'.Permission::VoirHistoriqueTransactions->value)
                ->name('historique.index');
            Route::get('/historique/donnees', [Partenaire\TransactionController::class, 'donnees'])
                ->middleware('permission:'.Permission::VoirHistoriqueTransactions->value)
                ->name('historique.donnees');
        });
});
