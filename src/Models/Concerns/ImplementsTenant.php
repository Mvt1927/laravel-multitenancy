<?php

namespace Spatie\Multitenancy\Models\Concerns;

use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Facades\Octane;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Contracts\IsDomain;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Exceptions\InvalidConfiguration;
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

        static::updated(function (IsTenant $tenant) {
            $domainKey = config('multitenancy.domain_key', 'domain');

            if ($tenant instanceof \Illuminate\Database\Eloquent\Model && $tenant->wasChanged($domainKey)) {
                $originalDomain = $tenant->getOriginal($domainKey);

                if (is_string($originalDomain) && $originalDomain !== '') {
                    $tenant->forgetDomainsCache([$originalDomain]);
                }
            }

            $tenant->forgetDomainCache();
        });
    }

    public function forgetDomainCache(): void
    {
        $domainModel = config('multitenancy.domain_model');
        $tenantModel = config('multitenancy.tenant_model', static::class);
        $domainKey = config('multitenancy.domain_key', 'domain');
        $isMultiDomain = ! empty($domainModel) && $domainModel !== $tenantModel;

        if ($isMultiDomain) {
            if (! method_exists($this, 'domains')) {
                throw InvalidConfiguration::domainRelationMissing(static::class);
            }

            $relationDomains = $this->relationLoaded('domains') ? $this->getRelation('domains') : $this->domains()->get();

            $domains = collect($relationDomains)
                ->map(function ($domainItem) use ($domainKey) {
                    if ($domainItem instanceof IsDomain) {
                        return $domainItem->getDomainName();
                    }

                    return is_object($domainItem) ? ($domainItem->{$domainKey} ?? null) : (string) $domainItem;
                })
                ->filter()
                ->unique()
                ->values()
                ->all();
        } else {
            $singleDomain = ($this instanceof IsDomain)
                ? $this->getDomainName()
                : ($this->{$domainKey} ?? null);

            $domains = (is_string($singleDomain) && $singleDomain !== '') ? [$singleDomain] : [];
        }

        $this->forgetDomainsCache($domains);
    }

    public function forgetDomainsCache(array $domains): void
    {
        $domains = array_values(array_filter(array_unique($domains)));

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
