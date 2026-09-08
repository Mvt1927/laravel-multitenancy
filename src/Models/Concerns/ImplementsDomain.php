<?php

declare(strict_types=1);

namespace Spatie\Multitenancy\Models\Concerns;

use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Contracts\IsDomain;
use Spatie\Multitenancy\Contracts\IsTenant;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ImplementsDomain
{
    public static function bootImplementsDomain(): void
    {
        static::updated(function (IsDomain $domain) {
            if ($domain instanceof IsTenant) {
                return;
            }

            $domainKey = config('multitenancy.domain_key', 'domain');
            $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');
            $storeName = config('multitenancy.domain_cache.store', 'global');

            try {
                $cache = Cache::store($storeName);
            } catch (\Throwable) {
                $cache = Cache::store();
            }

            if ($domain instanceof \Illuminate\Database\Eloquent\Model && $domain->wasChanged($domainKey)) {
                $originalDomain = $domain->getOriginal($domainKey);
                if (is_string($originalDomain) && $originalDomain !== '') {
                    $cache->forget($prefix . $originalDomain);
                }
            }

            if ($domainName = $domain->getDomainName()) {
                $cache->forget($prefix . $domainName);
            }
        });

        static::deleted(function (IsDomain $domain) {
            if ($domain instanceof IsTenant) {
                return;
            }

            $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');
            $storeName = config('multitenancy.domain_cache.store', 'global');

            try {
                $cache = Cache::store($storeName);
            } catch (\Throwable) {
                $cache = Cache::store();
            }

            if ($domainName = $domain->getDomainName()) {
                $cache->forget($prefix . $domainName);
            }
        });
    }

    public function getDomainName(): ?string
    {
        $domainKey = config('multitenancy.domain_key', 'domain');

        return $this->{$domainKey} ?? null;
    }

    public function getTenant(): ?IsTenant
    {
        if ($this instanceof IsTenant) {
            return $this;
        }

        if (method_exists($this, 'tenant')) {
            return $this->relationLoaded('tenant') ? $this->getRelation('tenant') : $this->tenant()->first();
        }

        return null;
    }
}
