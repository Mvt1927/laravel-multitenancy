<?php

declare(strict_types=1);

namespace Spatie\Multitenancy;

use Illuminate\Support\Facades\Event;
use Laravel\Octane\Events\RequestReceived as OctaneRequestReceived;
use Laravel\Octane\Events\RequestTerminated as OctaneRequestTerminated;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Multitenancy\Commands\TenantFinderClearCommand;
use Spatie\Multitenancy\Commands\TenantsArtisanCommand;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsDomain;
use Spatie\Multitenancy\Contracts\IsTenant;

class MultitenancyServiceProvider extends PackageServiceProvider
{
    use UsesMultitenancyConfig;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-multitenancy')
            ->hasConfigFile()
            ->hasMigration('landlord/create_landlord_tenants_table')
            ->hasCommands([
                TenantsArtisanCommand::class,
                TenantFinderClearCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/multitenancy.php', 'multitenancy');
    }

    public function packageBooted(): void
    {
        if ($tenantModel = config('multitenancy.tenant_model')) {
            $this->app->bind(IsTenant::class, $tenantModel);
        }

        $domainModel = config('multitenancy.domain_model') ?? config('multitenancy.tenant_model');
        if ($domainModel) {
            $this->app->bind(IsDomain::class, $domainModel);
        }

        $this->app->bind(Multitenancy::class, fn ($app) => new Multitenancy($app));

        $this->detectsLaravelOctane();
    }

    protected function detectsLaravelOctane(): static
    {
        $isOctane = isset($_SERVER['LARAVEL_OCTANE']) && class_exists(OctaneRequestReceived::class);

        if (! $isOctane) {
            app(Multitenancy::class)->start();

            return $this;
        }

        Event::listen(OctaneRequestReceived::class, fn () => app(Multitenancy::class)->start());
        Event::listen(OctaneRequestTerminated::class, fn () => app(Multitenancy::class)->end());

        return $this;
    }
}
