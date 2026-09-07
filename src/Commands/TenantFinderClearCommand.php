<?php

declare(strict_types=1);

namespace Spatie\Multitenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class TenantFinderClearCommand extends Command
{
    protected $signature = 'tenant:clear {--domain= : The specific domain to clear from cache}';

    protected $description = 'Clear the tenant domain cache';

    public function handle(): int
    {
        $storeName = config('multitenancy.domain_cache.store', 'global');
        $enabledFlag = config('multitenancy.domain_cache.enabled_flag', 'tenant_finder_manual_cache');
        $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');

        $cache = $this->getCacheStore($storeName);

        if ($enabledFlag) {
            $cache->forget($enabledFlag);
            Cache::forget($enabledFlag);
        }

        $specificDomain = $this->option('domain');

        if (is_string($specificDomain) && $specificDomain !== '') {
            $cache->forget($prefix . $specificDomain);
            $this->info("Successfully cleared cache for domain: {$specificDomain}");

            return self::SUCCESS;
        }

        $tenantClass = config('multitenancy.tenant_model');

        if (! $tenantClass || ! class_exists($tenantClass)) {
            $this->warn('Tenant model not configured or class does not exist.');

            return self::SUCCESS;
        }

        $count = 0;

        try {
            $tenantClass::query()->chunk(100, function ($tenants) use ($cache, $prefix, &$count) {
                foreach ($tenants as $tenant) {
                    $domains = [];

                    if (isset($tenant->domain) && is_string($tenant->domain) && $tenant->domain !== '') {
                        $domains[] = $tenant->domain;
                    }

                    if (method_exists($tenant, 'domains')) {
                        try {
                            $relationDomains = $tenant->domains;

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

                    foreach (array_unique(array_filter($domains)) as $domain) {
                        $cache->forget($prefix . $domain);
                        $count++;
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->warn('Could not query tenants: ' . $e->getMessage());
        }

        $this->info("Successfully cleared domain cache for {$count} domain(s).");

        return self::SUCCESS;
    }

    protected function getCacheStore(?string $storeName): CacheRepository
    {
        try {
            return Cache::store($storeName);
        } catch (\Throwable) {
            return Cache::store();
        }
    }
}
