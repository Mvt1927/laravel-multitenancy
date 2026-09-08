<?php

declare(strict_types=1);

namespace Spatie\Multitenancy\Contracts;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
interface IsDomain
{
    public function getDomainName(): ?string;

    public function getTenant(): ?IsTenant;
}
