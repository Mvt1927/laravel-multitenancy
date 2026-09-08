<?php

declare(strict_types=1);

namespace Spatie\Multitenancy\Models\Concerns;

use Spatie\Multitenancy\Contracts\IsTenant;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait ImplementsDomain
{
    public function getDomainName(): ?string
    {
        $domainKey = config('multitenancy.domain_key', 'domain');

        return $this->{$domainKey} ?? null;
    }

    public function getTenant(): ?IsTenant
    {
        if ($this instanceof IsTenant) {
            return $this;
        }

        if (method_exists($this, 'tenant')) {
            return $this->relationLoaded('tenant') ? $this->getRelation('tenant') : $this->tenant()->first();
        }

        return null;
    }
}
