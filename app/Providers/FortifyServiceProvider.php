<?php

namespace App\Providers;

use App\Actions\Auth\AuthentifierUtilisateur;
use App\Actions\Auth\ConfirmerMotDePasse;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Http\Responses\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(LoginRequest::class, ConnexionRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Heure d'affichage du formulaire : un envoi trop rapide trahit un robot (ConnexionRequest).
        Fortify::loginView(function () {
            session()->put(ConnexionRequest::CLE_AFFICHAGE, now()->getTimestamp());

            return view('auth.connexion');
        });
        Fortify::confirmPasswordView(fn () => view('auth.confirmer-mot-de-passe'));

        Fortify::authenticateUsing(app(AuthentifierUtilisateur::class));
        Fortify::confirmPasswordsUsing(app(ConfirmerMotDePasse::class));

        $this->configurerLimitesDeConnexion();
    }

    /**
     * Le PIN à 5 chiffres n'offre que 100 000 combinaisons : on borne les
     * essais par couple (nom d'utilisateur, IP) et, plus largement, par IP
     * pour empêcher de tester un même PIN sur de nombreux comptes.
     */
    private function configurerLimitesDeConnexion(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $nomUtilisateur = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));
            $reponse = fn (Request $request, array $headers) => back()
                ->withInput($request->only(Fortify::username()))
                ->withErrors([Fortify::username() => __('Trop de tentatives de connexion. Réessayez dans :secondes secondes.', [
                    'secondes' => $headers['Retry-After'] ?? 60,
                ])]);

            return [
                Limit::perMinute((int) config('plateforme.connexion.tentatives_par_minute'))
                    ->by('utilisateur:'.$nomUtilisateur.'|'.$request->ip())
                    ->response($reponse),
                Limit::perMinute((int) config('plateforme.connexion.tentatives_par_minute_par_ip'))
                    ->by('ip:'.$request->ip())
                    ->response($reponse),
            ];
        });
    }
}
