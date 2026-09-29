<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\JournalAudit;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Routes authentifiées légitimement dépourvues de permission métier
 * (aiguillage, déconnexion, confirmation d'identité).
 */
const ROUTES_AUTHENTIFIEES_SANS_PERMISSION = [
    'accueil-espace',
    'logout',
    'password.confirm',
    'password.confirm.store',
    'password.confirmation',
];

it('requires a permission middleware on every authenticated route', function () {
    $sansPermission = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => in_array('auth', $route->gatherMiddleware(), true)
            || collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:')))
        ->reject(fn (RouteDefinition $route) => in_array($route->getName(), ROUTES_AUTHENTIFIEES_SANS_PERMISSION, true))
        ->reject(fn (RouteDefinition $route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:')))
        ->map(fn (RouteDefinition $route) => $route->getName() ?? $route->uri())
        ->values()
        ->all();

    expect($sansPermission)->toBe([]);
});

it('exposes no route able to modify the audit log, only the guarded manual purge', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->filter(fn (RouteDefinition $route) => str_contains($route->uri(), 'journal') || str_contains($route->uri(), 'audit'))
        ->values();

    // Seule exception : la purge manuelle motivée (PurgerJournalAudit, inscrite au
    // registre), réservée à sa permission et confirmée par le mot de passe.
    expect($routes->map(fn (RouteDefinition $route) => $route->getName())->all())->toBe(['gestion.journal.purger'])
        ->and($routes->first()->methods())->toBe(['POST'])
        ->and($routes->first()->gatherMiddleware())
        ->toContain('permission:'.Permission::PurgerJournalAudit->value, 'password.confirm:password.confirm,300');
});

it('sends security headers on every page', function () {
    $this->get(route('login'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('forbids caching of authenticated pages', function () {
    $reponse = connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.tableau-de-bord'));

    expect($reponse->headers->get('Cache-Control'))->toContain('no-store');
});

it('rejects an oversized or malformed username before any lookup', function (string $nomUtilisateur) {
    $this->from(route('login'))
        ->post(route('login.store'), ['nom_utilisateur' => $nomUtilisateur, 'password' => '12345'])
        ->assertSessionHasErrors('nom_utilisateur');

    expect(JournalAudit::where('action', 'connexion.echec')->exists())->toBeFalse();
})->with([
    'trop long' => [str_repeat('a', 51)],
    'injection sql' => ["admin' OR '1'='1"],
    'balise html' => ['<script>alert(1)</script>'],
]);

it('never keeps sensitive fields in the flashed old input', function () {
    Route::middleware('web')->post('/_test/formulaire-sensible', function (Request $request) {
        $request->validate(['champ_obligatoire' => 'required']);
    });

    $this->from('/')->post('/_test/formulaire-sensible', [
        'numero_piece_identite' => 'CI0012345678',
        'code' => '654321',
        'pin' => '48157',
        'nom' => 'Kouamé',
    ]);

    expect(session()->getOldInput())->toBe(['nom' => 'Kouamé']);
});

it('counts wrong pins on password confirmation towards the account lock', function () {
    config(['plateforme.connexion.echecs_avant_verrouillage' => 3]);
    $admin = utilisateurAvecRole(Role::Admin);

    foreach (range(1, 3) as $essai) {
        connecter($admin)->post(route('password.confirm.store'), ['password' => '00000']);
    }

    expect($admin->fresh()->estVerrouille())->toBeTrue();

    connecter($admin->fresh())->get(route('gestion.tableau-de-bord'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('confirms the password with the correct pin', function () {
    $admin = utilisateurAvecRole(Role::Admin);

    connecter($admin)->post(route('password.confirm.store'), ['password' => UserFactory::PIN])
        ->assertSessionHasNoErrors();

    expect(User::find($admin->id)->tentatives_echouees)->toBe(0);
});

it('ignores a remember-me request', function () {
    $agent = utilisateurAvecRole(Role::Agent);

    formulaireConnexionAffiche()->post(route('login.store'), [
        'nom_utilisateur' => $agent->nom_utilisateur,
        'password' => UserFactory::PIN,
        'remember' => 'on',
    ])->assertCookieMissing(auth()->guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($agent);
});

it('refuses mass assignment of the partner link and the account status', function () {
    $user = User::factory()->create();

    $user->fill(['partenaire_id' => 1]);
})->throws(MassAssignmentException::class);

it('sends a strict Content-Security-Policy: no inline script, no eval, no framing', function () {
    $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self'", "object-src 'none'", "frame-ancestors 'none'", "base-uri 'self'", "form-action 'self'")
        ->and(str($csp)->after('script-src')->before(';')->toString())->not->toContain('unsafe-inline')->not->toContain('unsafe-eval');
});

it('keeps views compatible with the CSP (no inline script, Alpine expressions without code)', function () {
    $interdits = [
        '/<script(?![^>]*(\bsrc=|type="application\/json"))[^>]*>/i' => 'script en ligne',
        '/\son[a-z]+\s*=\s*"/i' => 'gestionnaire on…= en ligne',
        '/(x-[a-z:.-]+|@[a-z.-]+)="[^"]*(=>|\bfunction\b|\bdocument\.|\bwindow\.|\bnavigator\.|setTimeout|\.replace\(\/)[^"]*"/i' => 'expression Alpine avec du code',
        '/x-data="\{[^"]*\(\)\s*\{/' => 'méthode déclarée dans x-data',
    ];

    $fautes = [];

    foreach (File::allFiles(resource_path('views')) as $fichier) {
        foreach ($interdits as $motif => $libelle) {
            if (preg_match($motif, $fichier->getContents(), $trouve)) {
                $fautes[] = "{$fichier->getRelativePathname()} : {$libelle} ({$trouve[0]})";
            }
        }
    }

    expect($fautes)->toBe([]);
});

it('shows the reset filter button only when a filter is active', function () {
    $admin = utilisateurAvecRole(Role::Admin);
    $bouton = fn (string $html) => preg_match('/<a href="[^"]*" class="btn btn-outline-secondary( d-none)?" data-reinitialiser/', $html, $m) ? ($m[1] ?? '') : null;

    expect($bouton(connecter($admin)->get(route('gestion.partenaires.index'))->getContent()))->toBe(' d-none')
        ->and($bouton(connecter($admin)->get(route('gestion.partenaires.index', ['statut' => 'actif']))->getContent()))->toBe('')
        ->and($bouton(connecter($admin)->get(route('gestion.cartes.index', ['page' => 2]))->getContent()))->toBe(' d-none');
});
