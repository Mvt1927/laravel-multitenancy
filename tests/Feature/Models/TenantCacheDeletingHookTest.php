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

it('clears original and new domain cache when tenant domain is updated', function () {
    $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');
    $store = config('multitenancy.domain_cache.store', 'global');

    try {
        $cache = Cache::store($store);
    } catch (\Throwable) {
        $cache = Cache::store();
    }

    $tenant = \Spatie\Multitenancy\Models\Tenant::factory()->create(['domain' => 'initial-domain.com']);

    $oldKey = $prefix . 'initial-domain.com';
    $newKey = $prefix . 'updated-domain.com';

    $cache->put($oldKey, 'cached-old', 3600);
    $cache->put($newKey, 'cached-new', 3600);

    expect($cache->has($oldKey))->toBeTrue()
        ->and($cache->has($newKey))->toBeTrue();

    $tenant->update(['domain' => 'updated-domain.com']);

    expect($cache->has($oldKey))->toBeFalse()
        ->and($cache->has($newKey))->toBeFalse();
});

it('clears domain cache when domain model is updated or deleted', function () {
    config()->set('multitenancy.domain_model', \Spatie\Multitenancy\Models\Domain::class);

    $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');
    $store = config('multitenancy.domain_cache.store', 'global');

    try {
        $cache = Cache::store($store);
    } catch (\Throwable) {
        $cache = Cache::store();
    }

    $tenant = \Spatie\Multitenancy\Models\Tenant::factory()->create();
    $domain = $tenant->domains()->create(['domain' => 'domain-lifecycle-old.com']);

    $oldKey = $prefix . 'domain-lifecycle-old.com';
    $newKey = $prefix . 'domain-lifecycle-new.com';

    $cache->put($oldKey, 'cached-val', 3600);
    expect($cache->has($oldKey))->toBeTrue();

    $domain->update(['domain' => 'domain-lifecycle-new.com']);
    expect($cache->has($oldKey))->toBeFalse();

    $cache->put($newKey, 'cached-new-val', 3600);
    expect($cache->has($newKey))->toBeTrue();

    $domain->delete();
    expect($cache->has($newKey))->toBeFalse();
});
