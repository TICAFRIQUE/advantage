<?php

use App\Enums\Role;
use App\Models\User;

it('shows in the sidebar only the entries the role is allowed to use', function (Role $role, array $visibles, array $absents) {
    $reponse = connecter(utilisateurAvecRole($role))->get(route('accueil-espace'))->assertRedirect();
    $page = connecter(User::latest('id')->first())->get($reponse->headers->get('Location'))->assertOk();

    foreach ($visibles as $libelle) {
        $page->assertSee($libelle, false);
    }

    foreach ($absents as $libelle) {
        $page->assertDontSee($libelle, false);
    }
})->with([
    'agent' => [Role::Agent, ['Tableau de bord', 'Activer une carte', 'Liste des cartes'], ['>Transaction<', 'Historique', 'SMS simulés']],
    'partenaire' => [Role::Partenaire, ['Tableau de bord', '>Transaction<', 'Historique'], ['Activer une carte', 'Liste des cartes']],
    'admin' => [Role::Admin, ['Tableau de bord', 'Activer une carte', 'Liste des cartes', 'Partenaires', '>Transaction<', 'SMS simulés'], ['Historique']],
]);

it('marks the current page in the sidebar', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.cartes.index'))
        ->assertSeeInOrder(['barre-laterale__lien actif', 'aria-current="page"', 'Liste des cartes'], false);
});

it('shows the account menu with initials, username, role and a logout form', function () {
    $agent = utilisateurAvecRole(Role::Agent, ['nom' => 'Koffi Yao Serge', 'nom_utilisateur' => 'kys']);

    connecter($agent)->get(route('gestion.tableau-de-bord'))
        ->assertSee('KS')
        ->assertSee('@kys')
        ->assertSee('Agent')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('Déconnexion');
});

it('shows the partner name in the account menu of a partner operator', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);

    connecter($operateur)->get(route('partenaire.tableau-de-bord'))
        ->assertSee($operateur->partenaire->nom);
});

it('renders the sidebar collapsed when the preference cookie says so', function (?string $cookie, bool $reduite) {
    $requete = connecter(utilisateurAvecRole(Role::Agent));

    if ($cookie !== null) {
        $requete->withUnencryptedCookie('barre_reduite', $cookie);
    }

    $html = $requete->get(route('gestion.tableau-de-bord'))->getContent();

    expect(str_contains($html, 'class="app-erp barre-reduite"'))->toBe($reduite);
})->with([
    'préférence réduite' => ['1', true],
    'préférence étendue' => ['0', false],
    'aucune préférence' => [null, false],
]);

it('builds avatar initials from the first and last words of the name', function (string $nom, string $initiales) {
    expect(User::factory()->make(['nom' => $nom])->initiales())->toBe($initiales);
})->with([
    ['Koffi Yao Serge', 'KS'],
    ['awa', 'A'],
    ['Élodie Ange', 'ÉA'],
]);
