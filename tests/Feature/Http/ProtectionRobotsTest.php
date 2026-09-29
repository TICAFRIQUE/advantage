<?php

use App\Enums\Role;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Robots;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Protection contre les robots et contre l'exposition du code / des clés
|--------------------------------------------------------------------------
*/

describe('sondes des scanners', function () {
    it('answers a bare 404 without session to paths the application never serves', function (string $chemin) {
        $reponse = $this->get($chemin);

        $reponse->assertNotFound();
        expect($reponse->getContent())->toBe('Page introuvable.')
            ->and($reponse->headers->getCookies())->toBeEmpty();
    })->with([
        '/.env', '/.env.production', '/.git/config', '/.aws/credentials', '/wp-login.php', '/wp-admin/install.php',
        '/xmlrpc.php', '/phpmyadmin/index.php', '/pma', '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php',
        '/config.php', '/info.phtml', '/cgi-bin/test.cgi', '/gestion/..%2F..%2F.env', '/%2Eenv',
        '/gestion/administration/parametres/sauvegardes/..%2F..%2Fcomposer.json', '/telescope/requests', '/_ignition/execute-solution',
    ]);

    it('blocks an address after repeated probes, then every page answers 403', function () {
        config(['plateforme.robots.sondes_avant_blocage' => 3]);

        foreach (['/.env', '/.git/HEAD', '/wp-login.php'] as $chemin) {
            $this->get($chemin)->assertNotFound();
        }

        $this->get(route('login'))->assertForbidden();
        expect(Robots::estBloquee('127.0.0.1'))->toBeTrue();
    });

    it('lets an administrator unblock an address', function () {
        config(['plateforme.robots.sondes_avant_blocage' => 1]);
        $this->get('/.env');
        $this->get(route('login'))->assertForbidden();

        $this->artisan('robots:debloquer', ['ip' => '127.0.0.1'])->assertSuccessful();

        $this->get(route('login'))->assertOk();
        $this->artisan('robots:debloquer', ['ip' => 'pas-une-ip'])->assertFailed();
    });

    it('never counts legitimate pages as probes', function () {
        config(['plateforme.robots.sondes_avant_blocage' => 2]);

        foreach (range(1, 3) as $i) {
            $this->get('/.well-known/acme-challenge/jeton');
            $this->get('/page-inexistante');
        }

        $this->get(route('login'))->assertOk();
        expect(Robots::estBloquee('127.0.0.1'))->toBeFalse();
    });
});

describe('outils de scan', function () {
    it('refuses scanning tools and requests without user agent', function (string $navigateur) {
        $this->withHeader('User-Agent', $navigateur)->get(route('login'))->assertForbidden();
    })->with(['', 'sqlmap/1.8', 'Mozilla/5.0 (compatible; Nmap Scripting Engine)', 'Nuclei - Open-source project', '${jndi:ldap://x/a}']);

    it('lets browsers, curl and the health check through', function () {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/140.0 Mobile Safari/537.36')->get(route('login'))->assertOk();
        $this->withHeader('User-Agent', 'curl/8.5.0')->get(route('login'))->assertOk();
        $this->withHeader('User-Agent', '')->get('/up')->assertOk();
    });
});

describe('en-têtes et indexation', function () {
    it('asks search engines not to index anything and never reveals the php version', function (string $chemin) {
        $reponse = $this->get($chemin);

        expect($reponse->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow, noarchive')
            ->and($reponse->headers->has('X-Powered-By'))->toBeFalse();
    })->with(['/login', '/up', '/.env', '/page-inexistante']);

    it('closes the whole site to crawlers', function () {
        expect(file_get_contents(public_path('robots.txt')))->toContain("User-agent: *\nDisallow: /");
    });

    it('never serves hidden files nor build manifests, and falls back to public/ from the project root', function () {
        $public = file_get_contents(public_path('.htaccess'));
        $racine = file_get_contents(base_path('.htaccess'));

        expect($public)->toContain('RewriteRule (^|/)\.(?!well-known(/|$)) - [F,L]')
            ->toContain('RewriteRule ^build/.*\.json$ - [F,L]')
            ->toContain('Header always unset X-Powered-By')
            ->and($racine)->toContain('RewriteRule ^(.*)$ public/$1 [L]')
            ->toContain('Require all denied');
    });

    it('does not serve private storage files over http', function () {
        expect(config('filesystems.disks.local.serve'))->toBeFalse()
            ->and(collect(Route::getRoutes()->getRoutes())->contains(fn ($route) => str_starts_with($route->uri(), 'storage/')))->toBeFalse();
    });
});

describe('limites de navigation', function () {
    it('limits visitors per address', function () {
        config(['plateforme.robots.requetes_par_minute_visiteur' => 3]);

        foreach (range(1, 3) as $i) {
            $this->get(route('login'))->assertOk();
        }

        $this->get(route('login'))->assertStatus(429);
    });

    it('limits logged-in users per account, not per shared address', function () {
        config(['plateforme.robots.requetes_par_minute_connecte' => 2]);
        $premier = utilisateurAvecRole(Role::Admin);
        $second = utilisateurAvecRole(Role::Admin);

        connecter($premier)->get(route('profil'))->assertOk();
        connecter($premier)->get(route('profil'))->assertOk();
        connecter($premier)->get(route('profil'))->assertStatus(429);

        connecter($second)->get(route('profil'))->assertOk();
    });
});

describe('connexion', function () {
    it('shows an invisible trap field, out of the keyboard path', function () {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="champ-piege" aria-hidden="true"', false)
            ->assertSee('name="'.ConnexionRequest::CHAMP_PIEGE.'"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSessionHas(ConnexionRequest::CLE_AFFICHAGE);
    });

    it('rejects a bot like a wrong pin, without touching the account', function (string $cas) {
        $agent = utilisateurAvecRole(Role::Agent);
        $saisie = ['nom_utilisateur' => $agent->nom_utilisateur, 'password' => UserFactory::PIN];

        $reponse = match ($cas) {
            'champ piège rempli' => formulaireConnexionAffiche()->post(route('login.store'), [...$saisie, ConnexionRequest::CHAMP_PIEGE => 'https://spam.example']),
            'formulaire jamais affiché' => $this->post(route('login.store'), $saisie),
            'envoi instantané' => $this->withSession([ConnexionRequest::CLE_AFFICHAGE => now()->getTimestamp()])->post(route('login.store'), $saisie),
        };

        $reponse->assertSessionHasErrors(['nom_utilisateur' => __('auth.failed')]);
        $this->assertGuest();
        expect(User::find($agent->id)->tentatives_echouees)->toBe(0);
    })->with(['champ piège rempli', 'formulaire jamais affiché', 'envoi instantané']);

    it('logs in a human who opened the form, then typed', function () {
        $agent = utilisateurAvecRole(Role::Agent);

        $this->get(route('login'))->assertOk();
        $this->travel(3)->seconds();

        $this->post(route('login.store'), ['nom_utilisateur' => $agent->nom_utilisateur, 'password' => UserFactory::PIN, ConnexionRequest::CHAMP_PIEGE => ''])
            ->assertRedirect(route('accueil-espace'));
        $this->assertAuthenticatedAs($agent);
    });
});

describe('mode débogage', function () {
    it('is forced off in production, and flagged for the security check', function () {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        (new AppServiceProvider(app()))->register();

        expect(config('app.debug'))->toBeFalse()
            ->and(config('plateforme.securite.debug_neutralise'))->toBeTrue();
    });

    it('is left untouched outside production', function () {
        config(['app.debug' => true]);

        (new AppServiceProvider(app()))->register();

        expect(config('app.debug'))->toBeTrue();
    });
});
