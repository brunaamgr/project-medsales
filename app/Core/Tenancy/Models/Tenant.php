<?php

namespace App\Core\Tenancy\Models;

use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $subdomain
 * @property string $status
 */
#[Fillable(['name', 'slug', 'subdomain', 'status', 'settings'])]
class Tenant extends Model
{
    use HasPublicId, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->trashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
