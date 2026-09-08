<?php

declare(strict_types=1);

namespace Spatie\Multitenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Exceptions\InvalidConfiguration;

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

        $domainModel = config('multitenancy.domain_model');
        $domainKey = config('multitenancy.domain_key', 'domain');
        $isMultiDomain = ! empty($domainModel) && $domainModel !== $tenantClass;

        if ($isMultiDomain && ! method_exists($tenantClass, 'domains')) {
            throw InvalidConfiguration::domainRelationMissing($tenantClass);
        }

        try {
            $tenantClass::query()->chunk(100, function ($tenants) use ($cache, $prefix, $domainKey, $isMultiDomain, &$count) {
                foreach ($tenants as $tenant) {
                    if ($isMultiDomain) {
                        $relationDomains = $tenant->relationLoaded('domains') ? $tenant->getRelation('domains') : $tenant->domains()->get();

                        $domains = collect($relationDomains)
                            ->map(function ($domainItem) use ($domainKey) {
                                if ($domainItem instanceof \Spatie\Multitenancy\Contracts\IsDomain) {
                                    return $domainItem->getDomainName();
                                }

                                return is_object($domainItem) ? ($domainItem->{$domainKey} ?? null) : (string) $domainItem;
                            })
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    } else {
                        $singleDomain = ($tenant instanceof \Spatie\Multitenancy\Contracts\IsDomain)
                            ? $tenant->getDomainName()
                            : ($tenant->{$domainKey} ?? null);

                        $domains = (is_string($singleDomain) && $singleDomain !== '') ? [$singleDomain] : [];
                    }

                    foreach ($domains as $domain) {
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
