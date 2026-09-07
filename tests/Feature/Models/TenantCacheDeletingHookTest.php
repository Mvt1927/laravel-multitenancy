<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

class DummyTenantWithCache extends Model implements IsTenant
{
    use ImplementsTenant;

    protected $guarded = [];

    public $domain;
}

it('clears domain cache using forgetDomainCache', function () {
    $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');
    $store = config('multitenancy.domain_cache.store', 'global');

    try {
        $cache = Cache::store($store);
    } catch (\Throwable) {
        $cache = Cache::store();
    }

    $tenant = new DummyTenantWithCache();
    $tenant->domain = 'example-delete.com';

    $cacheKey = $prefix . 'example-delete.com';
    $cache->put($cacheKey, 'some-value', 3600);
    expect($cache->has($cacheKey))->toBeTrue();

    $tenant->forgetDomainCache();

    expect($cache->has($cacheKey))->toBeFalse();
});
