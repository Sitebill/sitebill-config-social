<?php

namespace App\Configs\Social;

use App\Configs\Social\Console\RecountCommand;
use App\Configs\Social\Console\SeedDemoCommand;
use App\Configs\Social\Support\SocialAccess;
use App\Http\Middleware\ResolveTenantFromDomain;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Точка входа backend-части конфигурации `social` — социальная сеть (docs/SOCIAL_APP.md).
 * Регистрируется ядром через запись 'provider' в apps/api/config/configurations.php.
 */
class SocialConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Способности для @can(ability: "social.*") в схеме: без model Lighthouse вызывает Gate::check(ability).
        foreach (SocialAccess::abilities() as $ability) {
            Gate::define($ability, fn ($user) => SocialAccess::can($user, $ability));
        }

        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', ResolveTenantFromDomain::class])
                ->prefix('api/v1/social')->name('social.')
                ->group(__DIR__.'/../routes/api.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([RecountCommand::class, SeedDemoCommand::class]);
        }
    }
}
