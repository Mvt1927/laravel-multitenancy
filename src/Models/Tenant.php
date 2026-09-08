<?php

namespace Spatie\Multitenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Contracts\IsDomain;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\HasDomains;
use Spatie\Multitenancy\Models\Concerns\ImplementsDomain;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class Tenant extends Model implements IsTenant, IsDomain
{
    use UsesLandlordConnection;
    use ImplementsTenant;
    use ImplementsDomain;
    use HasDomains;
    use HasFactory;

    protected $guarded = [];
}
