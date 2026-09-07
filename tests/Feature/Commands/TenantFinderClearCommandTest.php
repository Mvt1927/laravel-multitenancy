<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

it('clears specific domain cache via --domain option', function () {
    $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');
    $store = config('multitenancy.domain_cache.store', 'global');

    try {
        $cache = Cache::store($store);
    } catch (\Throwable) {
        $cache = Cache::store();
    }

    $cache->put($prefix . 'custom.domain.test', 'some-tenant', 3600);
    expect($cache->has($prefix . 'custom.domain.test'))->toBeTrue();

    $this->artisan('tenant:clear --domain=custom.domain.test')
        ->assertSuccessful();

    expect($cache->has($prefix . 'custom.domain.test'))->toBeFalse();
});

it('clears the enabled flag from cache', function () {
    $enabledFlag = config('multitenancy.domain_cache.enabled_flag', 'tenant_finder_manual_cache');
    $store = config('multitenancy.domain_cache.store', 'global');

    try {
        $cache = Cache::store($store);
    } catch (\Throwable) {
        $cache = Cache::store();
    }

    $cache->put($enabledFlag, true, 3600);
    expect($cache->has($enabledFlag))->toBeTrue();

    $this->artisan('tenant:clear')
        ->assertSuccessful();

    expect($cache->has($enabledFlag))->toBeFalse();
});
