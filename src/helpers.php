<?php

declare(strict_types=1);

use Spatie\Multitenancy\Contracts\IsTenant;

if (! function_exists('tenant')) {
    function tenant(): ?IsTenant
    {
        $tenantClass = config('multitenancy.tenant_model');

        return $tenantClass ? $tenantClass::current() : null;
    }
}
