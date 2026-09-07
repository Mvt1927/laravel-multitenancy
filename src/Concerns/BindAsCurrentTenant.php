<?php

namespace Spatie\Multitenancy\Concerns;

use Illuminate\Support\Facades\Context;
use Spatie\Multitenancy\Contracts\IsTenant;

trait BindAsCurrentTenant
{
    protected function bindAsCurrentTenant(IsTenant $tenant): static
    {
        $contextKey = config('multitenancy.current_tenant_context_key');
        $containerKey = config('multitenancy.current_tenant_container_key');

        if (class_exists(Context::class)) {
            Context::forget($contextKey);
        }

        app()->forgetInstance($containerKey);

        app()->instance($containerKey, $tenant);

        if (class_exists(Context::class)) {
            Context::add($contextKey, $tenant->getKey());
        }

        return $this;
    }
}
