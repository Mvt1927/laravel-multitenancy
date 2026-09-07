<?php

namespace Spatie\Multitenancy\TenantFinder;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Contracts\IsTenant;

class DomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $host = $request->getHost();
        $enabledFlag = config('multitenancy.domain_cache.enabled_flag', 'tenant_finder_manual_cache');
        $isCacheEnabled = (bool) config("multitenancy.{$enabledFlag}", config('multitenancy.tenant_finder_manual_cache', false));

        if ($isCacheEnabled) {
            $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');
            $cacheKey = $prefix . $host;
            $cache = $this->getCacheStore();

            if ($cache->has($cacheKey)) {
                return $cache->get($cacheKey);
            }

            $tenant = $this->resolveTenant($host);

            if ($tenant instanceof IsTenant) {
                $cache->forever($cacheKey, $tenant);
            }

            return $tenant;
        }

        return $this->resolveTenant($host);
    }

    protected function resolveTenant(string $host): ?IsTenant
    {
        $tenantModel = app(IsTenant::class);

        if (method_exists($tenantModel, 'domains')) {
            try {
                $tenant = $tenantModel::whereHas('domains', fn ($query) => $query->where('domain', $host))->first();

                if ($tenant) {
                    return $tenant;
                }
            } catch (\Throwable) {
            }
        }

        try {
            return $tenantModel::where('domain', $host)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getCacheStore(): CacheRepository
    {
        $storeName = config('multitenancy.domain_cache.store', 'global');

        try {
            return Cache::store($storeName);
        } catch (\Throwable) {
            return Cache::store();
        }
    }
}
