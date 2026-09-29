<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\EcheancesCartes;
use App\Services\PartenaireCourant;
use App\Services\Sms\PasserelleSms;
use App\Services\Sms\PasserelleSmsSimulee;
use App\Services\Sms\PasserelleSmsTicafrique;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Pilote SMS actif. La simulation n'envoie rien : elle est refusée en
        // production pour qu'aucun code OTP ne soit « envoyé » dans le vide.
        $this->app->bind(PasserelleSms::class, function (Application $app): PasserelleSms {
            $pilote = (string) config('plateforme.sms.driver');

            return match ($pilote) {
                'simulation' => $app->isProduction()
                    ? throw new RuntimeException('Le pilote SMS « simulation » est interdit en production : configurez SMS_DRIVER.')
                    : new PasserelleSmsSimulee,
                'ticafrique' => new PasserelleSmsTicafrique(
                    (string) config('services.ticafrique.url'),
                    (string) config('services.ticafrique.cle'),
                    (string) (config('services.ticafrique.expediteur') ?: config('plateforme.sms.expediteur')),
                    (int) config('services.ticafrique.delai_secondes', 10),
                ),
                default => throw new InvalidArgumentException("Pilote SMS inconnu : « {$pilote} »."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Le superadmin est autorisé partout, y compris sur les permissions
        // ajoutées ultérieurement. Retourner null laisse les autres règles s'appliquer.
        // Exception : les capacités de UserPolicy protègent l'intégrité des comptes
        // (jamais son propre compte, jamais un superadmin) et s'appliquent à tous.
        Gate::before(fn (User $user, string $capacite) => $user->hasRole(Role::Superadmin)
            && ! in_array($capacite, UserPolicy::CAPACITES, true) ? true : null);

        // Signale en développement toute tentative d'affecter un attribut non
        // autorisé (protection mass assignment rendue visible).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventLazyLoading(! $this->app->isProduction());

        // Production : toutes les URL générées en HTTPS (liens, redirections, formulaires),
        // même derrière un proxy qui termine le TLS.
        URL::forceHttps($this->app->isProduction());

        // Cloche des échéances dans l'en-tête du back-office (données en cache).
        View::composer('components.layouts.app', function ($vue): void {
            $user = auth()->user();

            $vue->with('echeancesEntete', $user?->estDuBackOffice() && $user->can(Permission::VoirCartes->value)
                ? EcheancesCartes::resume()
                : null);
        });

        RateLimiter::for('activation-carte', fn (Request $request) => Limit::perMinute(30)->by('agent:'.$request->user()?->id));
        // Anti-énumération des numéros de carte : par opérateur et par partenaire.
        RateLimiter::for('verification-carte', fn (Request $request) => [
            Limit::perMinute((int) config('plateforme.verification.par_minute_par_operateur'))->by('operateur:'.$request->user()?->id),
            Limit::perMinute((int) config('plateforme.verification.par_minute_par_partenaire'))
                ->by('partenaire:'.($request->user() ? PartenaireCourant::pour($request->user())?->id : 'aucun')),
        ]);
        RateLimiter::for('confirmation-otp', fn (Request $request) => Limit::perMinute(20)->by('operateur:'.$request->user()?->id));
        // Test d'envoi réel : chaque SMS consomme des unités chez le fournisseur.
        RateLimiter::for('test-sms', fn (Request $request) => [
            Limit::perHour(5)->by('test-sms:'.$request->user()?->id),
            Limit::perDay(20)->by('test-sms:global'),
        ]);
        RateLimiter::for('recherche-titulaire', fn (Request $request) => Limit::perMinute(60)->by('agent:'.$request->user()?->id));
    }
}
