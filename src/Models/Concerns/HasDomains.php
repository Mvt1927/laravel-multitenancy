<?php

declare(strict_types=1);

namespace Spatie\Multitenancy\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Domain;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasDomains
{
    public function domains(): HasMany
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $domainModel */
        $domainModel = config('multitenancy.domain_model') ?: Domain::class;

        return $this->hasMany($domainModel, 'tenant_id');
    }
}
