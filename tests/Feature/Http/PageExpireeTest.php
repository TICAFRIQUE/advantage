<?php

use App\Enums\Role;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

describe('jeton CSRF expiré', function () {
    /*
     * La protection CSRF est inactive en test : on la remplace par un
     * middleware qui lève l'exception qu'elle produirait (jeton expiré).
     */
    beforeEach(function () {
        $this->app->instance(PreventRequestForgery::class, new class
        {
            public function handle($request, $next)
            {
                throw new TokenMismatchException('CSRF token mismatch.');
            }
        });
    });

    it('sends an expired login form back to the login page with a clear message', function () {
        $this->post(route('login.store'), ['nom_utilisateur' => 'agent.demo', 'password' => '12345'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['nom_utilisateur' => 'Votre page a expiré. Veuillez réessayer.'])
            ->assertSessionHasInput('nom_utilisateur', 'agent.demo');

        expect(session()->getOldInput())->not->toHaveKey('password');
    });

    it('sends any other expired form back with a flash message', function () {
        Route::middleware('web')->post('/_test/formulaire', fn () => 'ok');

        $this->from('/')->post('/_test/formulaire')->assertRedirect('/')->assertSessionHas('erreur');
    });

    it('still answers 419 to json requests', function () {
        $this->postJson(route('login.store'), [])->assertStatus(419);
    });
});

it('forbids browser caching of every page', function (Closure $requete) {
    expect($requete()->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    'connexion' => [fn () => test()->get(route('login'))],
    'espace agent' => [fn () => connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.tableau-de-bord'))],
]);
