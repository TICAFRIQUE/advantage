<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Le superadmin est autorisé partout, y compris sur les permissions
        // ajoutées ultérieurement. Retourner null laisse les autres règles s'appliquer.
        Gate::before(fn (User $user) => $user->hasRole(Role::Superadmin) ? true : null);

        // Signale en développement toute tentative d'affecter un attribut non
        // autorisé (protection mass assignment rendue visible).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        RateLimiter::for('activation-carte', fn (Request $request) => Limit::perMinute(30)->by('agent:'.$request->user()?->id));
        RateLimiter::for('recherche-titulaire', fn (Request $request) => Limit::perMinute(60)->by('agent:'.$request->user()?->id));
    }
}
