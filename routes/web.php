<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\AccueilEspaceController;
use App\Http\Controllers\Gestion;
use App\Http\Controllers\Partenaire;
use App\Http\Controllers\ProfilController;
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

    // Mon profil : tout compte d'un des deux espaces (lecture seule).
    Route::get('/profil', ProfilController::class)
        ->middleware('permission:'.Permission::AccederGestion->value.'|'.Permission::AccederEspacePartenaire->value)
        ->name('profil');

    /*
    |----------------------------------------------------------------------
    | Back-office
    |----------------------------------------------------------------------
    */
    Route::prefix('gestion')->name('gestion.')
        ->middleware([
            'espace.gestion',
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
                        ->middleware(['permission:'.Permission::ReinitialiserPin->value, 'password.confirm:password.confirm,300', 'throttle:activation-carte'])
                        ->name('pin');
                    Route::get('/modifier', [Gestion\CompteController::class, 'edit'])->name('edit');
                    Route::put('/', [Gestion\CompteController::class, 'update'])->name('update');
                    Route::post('/verrouillage', [Gestion\CompteController::class, 'verrouillage'])->name('verrouillage');
                    Route::post('/statut', [Gestion\CompteController::class, 'statut'])->name('statut');
                    Route::delete('/', [Gestion\CompteController::class, 'supprimer'])
                        ->middleware(['permission:'.Permission::SupprimerComptes->value, 'password.confirm:password.confirm,300'])
                        ->name('supprimer');
                });

            // Exports des listes (CSV, Excel, PDF) : permission d'export + droit de
            // consulter la liste (vérifié par la Form Request de la liste).
            Route::get('/exports/{liste}/{format}', Gestion\ExportController::class)
                ->whereIn('liste', array_keys(Gestion\ExportController::LISTES))
                ->middleware(['permission:'.Permission::ExporterDonnees->value, 'throttle:10,1'])
                ->name('exports');

            // Paramètres › Utilisateurs du back-office
            Route::prefix('parametres/utilisateurs')->name('utilisateurs.')
                ->middleware('permission:'.Permission::GererUtilisateurs->value)
                ->group(function () {
                    Route::get('/', [Gestion\UtilisateurController::class, 'index'])->name('index');
                    Route::get('/donnees', [Gestion\UtilisateurController::class, 'donnees'])->name('donnees');
                    Route::get('/creer', [Gestion\UtilisateurController::class, 'create'])->name('create');
                    Route::post('/', [Gestion\UtilisateurController::class, 'store'])
                        ->middleware('throttle:activation-carte')
                        ->name('store');
                    Route::get('/{compte}', [Gestion\UtilisateurController::class, 'show'])->whereNumber('compte')->name('show');
                });

            // Paramètres › Rôles et permissions (anti-élévation : GardeDroits)
            Route::prefix('parametres/roles')->name('roles.')
                ->middleware('permission:'.Permission::GererRoles->value)
                ->group(function () {
                    Route::get('/', [Gestion\RoleController::class, 'index'])->name('index');
                    Route::get('/creer', [Gestion\RoleController::class, 'create'])->name('create');
                    Route::post('/', [Gestion\RoleController::class, 'store'])
                        ->middleware('password.confirm:password.confirm,300')
                        ->name('store');
                    Route::get('/{role}/modifier', [Gestion\RoleController::class, 'edit'])->whereNumber('role')->name('edit');
                    Route::put('/{role}', [Gestion\RoleController::class, 'update'])->whereNumber('role')
                        ->middleware('password.confirm:password.confirm,300')
                        ->name('update');
                    Route::delete('/{role}', [Gestion\RoleController::class, 'destroy'])->whereNumber('role')
                        ->middleware('password.confirm:password.confirm,300')
                        ->name('destroy');
                });

            // Administration › Paramètres : identité (nom, logo) et sauvegardes (superadmin).
            Route::prefix('administration/parametres')->name('parametres.')->group(function () {
                Route::get('/', [Gestion\ParametresController::class, 'index'])
                    ->middleware('permission:'.Permission::GererParametres->value.'|'.Permission::GererSauvegardes->value)
                    ->name('index');
                Route::put('/identite', [Gestion\ParametresController::class, 'identite'])
                    ->middleware('permission:'.Permission::GererParametres->value)
                    ->name('identite');

                Route::prefix('sauvegardes')->name('sauvegardes.')
                    ->middleware(['role:'.Role::Superadmin->value, 'permission:'.Permission::GererSauvegardes->value])
                    ->group(function () {
                        Route::post('/', [Gestion\SauvegardeController::class, 'store'])->middleware('throttle:5,1')->name('store');
                        Route::put('/dossier', [Gestion\SauvegardeController::class, 'dossier'])
                            ->middleware('password.confirm:password.confirm,300')->name('dossier');
                        Route::get('/{nom}', [Gestion\SauvegardeController::class, 'telecharger'])
                            ->middleware('password.confirm:password.confirm,300')->name('telecharger');
                        Route::post('/{nom}/restaurer', [Gestion\SauvegardeController::class, 'restaurer'])
                            ->middleware(['password.confirm:password.confirm,300', 'throttle:3,10'])->name('restaurer');
                        Route::delete('/{nom}', [Gestion\SauvegardeController::class, 'supprimer'])
                            ->middleware('password.confirm:password.confirm,300')->name('supprimer');
                    });
            });

            // Paramètres › Journal d'audit (lecture seule ; purge manuelle motivée)
            Route::prefix('parametres/journal')->name('journal.')->group(function () {
                Route::get('/', [Gestion\JournalAuditController::class, 'index'])
                    ->middleware('permission:'.Permission::VoirJournalAudit->value)
                    ->name('index');
                Route::get('/donnees', [Gestion\JournalAuditController::class, 'donnees'])
                    ->middleware('permission:'.Permission::VoirJournalAudit->value)
                    ->name('donnees');
                Route::post('/purger', [Gestion\JournalAuditController::class, 'purger'])
                    ->middleware(['permission:'.Permission::PurgerJournalAudit->value, 'password.confirm:password.confirm,300', 'throttle:5,1'])
                    ->name('purger');
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

            // Système : réservé au superadmin (rôle ET permission de l'espace
            // « superadmin », jamais attribuable à un autre rôle).
            Route::prefix('systeme')->middleware('role:'.Role::Superadmin->value)->group(function () {
                Route::get('/elements-supprimes', [Gestion\CorbeilleController::class, 'index'])
                    ->middleware('permission:'.Permission::RestaurerElements->value)
                    ->name('corbeille.index');
                Route::post('/elements-supprimes/partenaires/{partenaire}/restaurer', [Gestion\CorbeilleController::class, 'restaurerPartenaire'])
                    ->whereNumber('partenaire')->withTrashed()
                    ->middleware(['permission:'.Permission::RestaurerElements->value, 'password.confirm:password.confirm,300'])
                    ->name('corbeille.partenaires.restaurer');
                Route::post('/elements-supprimes/comptes/{compte}/restaurer', [Gestion\CorbeilleController::class, 'restaurerCompte'])
                    ->whereNumber('compte')->withTrashed()
                    ->middleware(['permission:'.Permission::RestaurerElements->value, 'password.confirm:password.confirm,300'])
                    ->name('corbeille.comptes.restaurer');
                Route::get('/test-sms', [Gestion\TestSmsController::class, 'index'])
                    ->middleware('permission:'.Permission::TesterSms->value)
                    ->name('sms-test.index');
                Route::post('/test-sms', [Gestion\TestSmsController::class, 'store'])
                    ->middleware(['permission:'.Permission::TesterSms->value, 'throttle:test-sms'])
                    ->name('sms-test.store');
                Route::get('/mise-en-production', [Gestion\MiseEnProductionController::class, 'show'])
                    ->middleware('permission:'.Permission::VoirCommandesProduction->value)
                    ->name('mise-en-production');
            });
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
