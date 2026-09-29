<?php

use App\Enums\Role;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\Partenaire;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EcheancesCartes;
use App\Services\StatistiquesTableauDeBord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Performance : le nombre de requêtes SQL d'une page ne dépend pas du volume
| de données (aucune requête par ligne), et reste sous un plafond raisonnable.
|--------------------------------------------------------------------------
*/

function peuplerDonnees(int $nombre, Partenaire $partenaire): void
{
    $agents = User::factory()->count(2)->create()->each(fn (User $u) => $u->assignRole(Role::Agent));
    // Données créées par un agent connecté : le journal a des auteurs dans les deux volumes.
    test()->actingAs($agents[0]);

    foreach (range(1, $nombre) as $i) {
        $carte = Carte::factory()->for($agents[$i % 2], 'activePar')->create(['active_le' => now()->subYear()->addDays(10 + $i)]);
        $demande = DemandeOtp::factory()->utilisee()->create(['carte_id' => $carte->id, 'partenaire_id' => $partenaire->id]);
        Transaction::factory()->create(['demande_otp_id' => $demande->id, 'valide_par_id' => $agents[0]->id]);
    }

    Partenaire::factory()->count($nombre)->create();
    EcheancesCartes::oublier();
    StatistiquesTableauDeBord::oublier();
}

/**
 * Mesure chaque page après un passage de chauffe (cache des permissions),
 * caches applicatifs vidés : les deux mesures partent du même état.
 *
 * @param  array<string, callable(): mixed>  $pages
 * @return Collection<string, int>
 */
function mesurerPages(array $pages): Collection
{
    foreach ($pages as $page) {
        $page();
    }

    return collect($pages)->map(function (callable $page): int {
        EcheancesCartes::oublier();
        StatistiquesTableauDeBord::oublier();

        return compterRequetes($page);
    });
}

function compterRequetes(callable $action): int
{
    $nombre = 0;
    DB::listen(function () use (&$nombre): void {
        $nombre++;
    });
    $action();
    DB::flushQueryLog();

    return $nombre;
}

/**
 * @return array<string, callable(): mixed>
 */
function pagesMesurees(User $admin, Partenaire $partenaire): array
{
    // Yajra répond 200 même en cas d'erreur (champ « error ») : on l'exige absent.
    $tableau = ['draw' => 1, 'start' => 0, 'length' => 50];

    return [
        'tableau de bord' => fn () => connecter($admin)->get(route('gestion.tableau-de-bord'))->assertOk(),
        'liste des cartes' => fn () => connecter($admin)->get(route('gestion.cartes.index'))->assertOk(),
        'rapport des cartes' => fn () => connecter($admin)->getJson(route('gestion.cartes.rapport.donnees', $tableau))->assertOk()->assertJsonMissingPath('error'),
        'partenaires' => fn () => connecter($admin)->getJson(route('gestion.partenaires.donnees', $tableau))->assertOk()->assertJsonMissingPath('error'),
        'fiche partenaire' => fn () => connecter($admin)->get(route('gestion.partenaires.show', $partenaire))->assertOk(),
        'transactions' => fn () => connecter($admin)->getJson(route('gestion.transactions.rapport.donnees', $tableau))->assertOk()->assertJsonMissingPath('error'),
        'utilisateurs' => fn () => connecter($admin)->getJson(route('gestion.utilisateurs.donnees', $tableau))->assertOk()->assertJsonMissingPath('error'),
        'journal' => fn () => connecter($admin)->getJson(route('gestion.journal.donnees', $tableau))->assertOk()->assertJsonMissingPath('error'),
    ];
}

it('runs the same number of queries whatever the volume of data', function () {
    $admin = utilisateurAvecRole(Role::Admin);
    $partenaire = Partenaire::factory()->create();

    peuplerDonnees(3, $partenaire);
    $peu = mesurerPages(pagesMesurees($admin, $partenaire));

    peuplerDonnees(25, $partenaire);
    $beaucoup = mesurerPages(pagesMesurees($admin, $partenaire));

    foreach ($peu as $page => $requetes) {
        expect($beaucoup[$page])->toBe($requetes, "« {$page} » : {$requetes} requêtes avec peu de données, {$beaucoup[$page]} avec beaucoup (requête par ligne ?)");
    }
});

it('keeps every page under a reasonable query ceiling', function () {
    $admin = utilisateurAvecRole(Role::Admin);
    $partenaire = Partenaire::factory()->create();
    peuplerDonnees(10, $partenaire);

    foreach (pagesMesurees($admin, $partenaire) as $page => $action) {
        expect(compterRequetes($action))->toBeLessThanOrEqual(40, "« {$page} » dépasse 40 requêtes");
    }
});

it('serves the dashboard statistics from the cache until data changes', function () {
    $admin = utilisateurAvecRole(Role::Admin);
    connecter($admin)->get(route('gestion.tableau-de-bord'))->assertOk();

    $avecCache = compterRequetes(fn () => connecter($admin)->get(route('gestion.tableau-de-bord')));
    StatistiquesTableauDeBord::oublier();
    EcheancesCartes::oublier();
    $sansCache = compterRequetes(fn () => connecter($admin)->get(route('gestion.tableau-de-bord')));

    expect($avecCache)->toBeLessThan($sansCache);

    // Une nouvelle carte invalide le cache : l'indicateur est à jour immédiatement.
    connecter($admin)->get(route('gestion.tableau-de-bord'));
    $avant = StatistiquesTableauDeBord::globales()['cartes_actives'];
    Carte::factory()->create();

    expect(StatistiquesTableauDeBord::globales()['cartes_actives'])->toBe($avant + 1);
});
