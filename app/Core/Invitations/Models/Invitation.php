<?php

namespace App\Core\Invitations\Models;

use App\Core\Roles\Models\Role;
use App\Core\Tenancy\BelongsToTenant;
use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $public_id
 * @property string $email
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property-read Role $role
 */
#[Fillable(['email', 'role_id', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'created_by'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    use BelongsToTenant, HasPublicId;

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'aceito',
            $this->revoked_at !== null => 'cancelado',
            $this->isPending() => 'pendente',
            default => 'expirado',
        };
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return Builder<self> */
    public static function open(): Builder
    {
        return self::query()->whereNull('accepted_at')->whereNull('revoked_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
