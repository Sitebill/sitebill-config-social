<?php

namespace App\Configs\Social\Console;

use App\Configs\Social\Demo\SocialDemo;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Sitebill\AppSdk\Host;

/** Демо-данные соцсети для сайта (по умолчанию — основной сайт; в Community он единственный). */
class SeedDemoCommand extends Command
{
    protected $signature = 'social:seed-demo {--tenant= : id сайта (по умолчанию основной)}';

    protected $description = 'Наполнить соцсеть примером данных';

    public function handle(): int
    {
        $tenant = $this->option('tenant')
            ? Tenant::withoutGlobalScopes()->find((int) $this->option('tenant'))
            : Tenant::withoutGlobalScopes()->where('is_default', true)->first();
        if (! $tenant) {
            $this->error('Сайт не найден.');

            return self::FAILURE;
        }
        // В консоли сайт не привязан: без runAs модели не отфильтруют по tenant_id.
        $res = Host::site()->runAs((int) $tenant->id, fn () => SocialDemo::seed());
        $this->info("Создано: пользователей {$res['users']}, постов {$res['posts']}.");

        return self::SUCCESS;
    }
}
