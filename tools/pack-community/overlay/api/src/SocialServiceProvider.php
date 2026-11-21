<?php

namespace SitebillApps\Rumantic\Social;

use Illuminate\Support\Facades\Gate;
use Sitebill\AppSdk\AppServiceProvider;
use SitebillApps\Rumantic\Social\Support\SocialAccess;

/**
 * Права для @can(ability: "sc.*") в схеме: Lighthouse без model вызывает Gate::check(ability).
 * REST-маршруты и миграции подключает рантайм приложений (api/routes/api.php, api/database/migrations).
 */
final class SocialServiceProvider extends AppServiceProvider
{
    protected function bootApp(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([Console\SeedDemoCommand::class, Console\RecountCommand::class]);
        }

        foreach (SocialAccess::abilities() as $ability) {
            Gate::define($ability, fn ($user) => SocialAccess::can($user, $ability));
        }
    }
}
