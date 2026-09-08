<?php

declare(strict_types=1);

namespace Spatie\Multitenancy\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Tenant;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTenant
{
    public function tenant(): BelongsTo
    {
        /** @var class-string<IsTenant> $tenantModel */
        $tenantModel = config('multitenancy.tenant_model') ?: Tenant::class;

        return $this->belongsTo($tenantModel, 'tenant_id');
    }
}
