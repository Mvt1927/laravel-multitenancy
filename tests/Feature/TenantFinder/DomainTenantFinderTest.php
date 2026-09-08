<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Contracts\IsDomain;
use Spatie\Multitenancy\Models\Domain;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\DomainTenantFinder;

beforeEach(function () {
    $this->tenantFinder = new DomainTenantFinder();
});

it('can find a tenant for the current domain', function () {
    $tenant = Tenant::factory()->create(['domain' => 'my-domain.com']);

    $request = Request::create('https://my-domain.com');

    expect($tenant->id)->toEqual($this->tenantFinder->findForRequest($request)->id);
});

it('will return null if there are no tenants', function () {
    $request = Request::create('https://my-domain.com');

    expect($this->tenantFinder->findForRequest($request))->toBeNull();
});

it('will return null if no tenant can be found the current domain', function () {
    Tenant::factory()->create(['domain' => 'my-domain.com']);

    $request = Request::create('https://another-domain.com');

    expect($this->tenantFinder->findForRequest($request))->toBeNull();
});

it('can find a tenant with multiple domains', function () {
    config()->set('multitenancy.domain_model', Domain::class);

    $tenant = Tenant::factory()->create();

    $tenant->domains()->create(['domain' => 'first-multi-domain.com']);
    $tenant->domains()->create(['domain' => 'second-multi-domain.com']);

    $firstRequest = Request::create('https://first-multi-domain.com');
    expect($tenant->id)->toEqual($this->tenantFinder->findForRequest($firstRequest)->id);

    $secondRequest = Request::create('https://second-multi-domain.com');
    expect($tenant->id)->toEqual($this->tenantFinder->findForRequest($secondRequest)->id);

    $unknownRequest = Request::create('https://unknown-multi-domain.com');
    expect($this->tenantFinder->findForRequest($unknownRequest))->toBeNull();
});

it('will forget all domain caches when a tenant with multiple domains is deleted', function () {
    config()->set('multitenancy.domain_model', Domain::class);
    config()->set('multitenancy.tenant_finder_manual_cache', true);

    try {
        $cache = Cache::store(config('multitenancy.domain_cache.store', 'global'));
    } catch (\Throwable) {
        $cache = Cache::store();
    }
    $prefix = config('multitenancy.domain_cache.prefix', 'tenant_by_domain:');

    $tenant = Tenant::factory()->create();

    $tenant->domains()->create(['domain' => 'cache-delete-1.com']);
    $tenant->domains()->create(['domain' => 'cache-delete-2.com']);

    $this->tenantFinder->findForRequest(Request::create('https://cache-delete-1.com'));
    $this->tenantFinder->findForRequest(Request::create('https://cache-delete-2.com'));

    expect($cache->has($prefix . 'cache-delete-1.com'))->toBeTrue()
        ->and($cache->has($prefix . 'cache-delete-2.com'))->toBeTrue();

    $tenant->delete();

    expect($cache->has($prefix . 'cache-delete-1.com'))->toBeFalse()
        ->and($cache->has($prefix . 'cache-delete-2.com'))->toBeFalse();
});

it('tenant and domain models correctly implement IsDomain and relationships', function () {
    $tenant = Tenant::factory()->create(['domain' => 'my-domain.com']);

    expect($tenant)->toBeInstanceOf(IsDomain::class)
        ->and($tenant->getDomainName())->toEqual('my-domain.com')
        ->and($tenant->getTenant()->id)->toEqual($tenant->id);

    $domain = $tenant->domains()->create(['domain' => 'custom-domain.com']);

    expect($domain)->toBeInstanceOf(IsDomain::class)
        ->and($domain->getDomainName())->toEqual('custom-domain.com')
        ->and($domain->tenant->id)->toEqual($tenant->id)
        ->and($domain->getTenant()->id)->toEqual($tenant->id);
});
