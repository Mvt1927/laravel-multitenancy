<?php

namespace Spatie\Multitenancy\Models\Concerns;

use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Facades\Octane;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantCollection;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ImplementsTenant
{
    public static function bootImplementsTenant(): void
    {
        static::deleting(function (IsTenant $tenant) {
            $tenant->forgetDomainCache();
        });
    }

    public function forgetDomainCache(): void
    {
        $domains = [];

        if (isset($this->domain) && is_string($this->domain) && $this->domain !== '') {
            $domains[] = $this->domain;
        }

        if (method_exists($this, 'domains')) {
            try {
                $relationDomains = $this->domains;

                if (is_iterable($relationDomains)) {
                    foreach ($relationDomains as $domainItem) {
                        if (is_string($domainItem)) {
                            $domains[] = $domainItem;
                        } elseif (is_object($domainItem) && isset($domainItem->domain)) {
                            $domains[] = $domainItem->domain;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        $domains = array_unique(array_filter($domains));

        if (empty($domains)) {
            return;
        }

        $storeName = config('multitenancy.domain_cache.store', 'global');
        $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');

        try {
            $cache = Cache::store($storeName);
        } catch (\Throwable) {
            $cache = Cache::store();
        }

        $tasks = [];

        foreach ($domains as $domain) {
            $tasks[] = fn () => $cache->forget($prefix . $domain);
        }

        if (count($tasks) >= 2 && class_exists(Octane::class) && (isset($_SERVER['LARAVEL_OCTANE']) || app()->bound('octane'))) {
            try {
                Octane::concurrently($tasks);

                return;
            } catch (\Throwable) {
            }
        }

        foreach ($tasks as $task) {
            $task();
        }
    }

    public function makeCurrent(): static
    {
        if ($this->isCurrent()) {
            return $this;
        }

        static::forgetCurrent();

        $this
            ->getMultitenancyActionClass(
                actionName: 'make_tenant_current_action',
                actionClass: MakeTenantCurrentAction::class
            )
            ->execute($this);

        return $this;
    }

    public function forget(): static
    {
        $this
            ->getMultitenancyActionClass(
                actionName: 'forget_current_tenant_action',
                actionClass: ForgetCurrentTenantAction::class
            )
            ->execute($this);

        return $this;
    }

    public static function current(): ?static
    {
        $containerKey = config('multitenancy.current_tenant_container_key');

        if (! app()->has($containerKey)) {
            return null;
        }

        return app($containerKey);
    }

    public static function checkCurrent(): bool
    {
        return static::current() !== null;
    }

    public function isCurrent(): bool
    {
        return static::current()?->getKey() === $this->getKey();
    }

    public static function forgetCurrent(): ?static
    {
        return tap(static::current(), fn (?IsTenant $tenant) => $tenant?->forget());
    }

    public function getDatabaseName(): string
    {
        return $this->database;
    }

    public function newCollection(array $models = []): TenantCollection
    {
        return new TenantCollection($models);
    }

    public function execute(callable $callable): mixed
    {
        $originalCurrentTenant = static::current();

        $this->makeCurrent();

        try {
            return $callable($this);
        } finally {
            $originalCurrentTenant
                ? $originalCurrentTenant->makeCurrent()
                : static::forgetCurrent();
        }
    }

    public function callback(callable $callable): \Closure
    {
        return fn () => $this->execute($callable);
    }
}
