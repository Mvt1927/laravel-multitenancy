<?php

namespace Spatie\Multitenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Contracts\IsDomain;
use Spatie\Multitenancy\Models\Concerns\HasTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsDomain;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class Domain extends Model implements IsDomain
{
    use UsesLandlordConnection;
    use ImplementsDomain;
    use HasTenant;
    use HasFactory;

    protected $table = 'domains';

    protected $guarded = [];
}
