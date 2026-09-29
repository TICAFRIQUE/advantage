<?php

use App\Enums\Permission;
use App\Enums\Role;

/**
 * @return list<string> libellés du menu du bas, dans l'ordre
 */
function libellesMenuBas(string $html): array
{
    preg_match('#<nav class="menu-bas d-lg-none"[^>]*>(.*?)</nav>#s', $html, $menu);
    preg_match_all('#<span>([^<]+)</span>\s*</(?:a|button)>#', $menu[1] ?? '', $libelles);

    return array_map('trim', $libelles[1]);
}

it('gives the admin Home, Cards, Activate (centre), Transaction and Menu', function () {
    $html = connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.cartes.index'))->getContent();

    expect(libellesMenuBas($html))->toBe(['Accueil', 'Cartes', 'Activer', 'Transaction', 'Menu'])
        ->and($html)->toMatch('/class="menu-bas__lien actif"\s+aria-current="page"/')
        ->toContain('menu-bas__lien menu-bas__principal')
        ->toContain('data-bs-target="#barre-laterale"');
});

it('adapts the bottom menu to the rights of an agent', function () {
    $html = connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.tableau-de-bord'))->getContent();

    expect(libellesMenuBas($html))->toBe(['Accueil', 'Cartes', 'Activer', 'Partenaires', 'Menu']);
});

it('puts the transaction in the centre when the account cannot activate cards', function () {
    $compte = utilisateurAvecRole(Role::Agent);
    Spatie\Permission\Models\Role::findByName('agent')->revokePermissionTo(Permission::ActiverCarte->value);
    $compte->givePermissionTo(Permission::EffectuerTransactionPartenaire->value);

    $html = connecter($compte->fresh())->get(route('gestion.tableau-de-bord'))->getContent();

    expect(libellesMenuBas($html))->toBe(['Accueil', 'Cartes', 'Transaction', 'Partenaires', 'Menu']);
});

it('gives partner users Home, History, Transaction (centre) and Profile', function () {
    $html = connecter(utilisateurAvecRole(Role::Partenaire))->get(route('partenaire.historique.index'))->getContent();

    expect(libellesMenuBas($html))->toBe(['Accueil', 'Historique', 'Transaction', 'Profil'])
        ->and($html)->toContain('href="'.route('partenaire.transaction.verifier').'" class="menu-bas__lien menu-bas__principal');
});

it('keeps the hamburger menu next to the bottom menu', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.tableau-de-bord'))
        ->assertSee('aria-label="Ouvrir le menu"', false)
        ->assertSee('class="menu-bas d-lg-none"', false);
});
