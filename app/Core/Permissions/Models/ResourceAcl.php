<?php

namespace App\Core\Permissions\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Concessão pontual de acesso a um recurso, com expiração opcional. */
#[Table('resource_acl')]
#[Fillable(['membership_id', 'permission_id', 'resource_type', 'resource_id', 'expires_at', 'created_by'])]
class ResourceAcl extends Model
{
    use BelongsToTenant;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
