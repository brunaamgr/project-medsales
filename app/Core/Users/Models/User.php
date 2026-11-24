<?php

namespace App\Core\Users\Models;

use App\Core\Permissions\AccessResolver;
use App\Core\Platform\Models\PlatformAdmin;
use App\Core\Tenancy\TenantContext;
use App\Shared\Support\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Identidade global; o vínculo com cada tenant está em Membership.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $email nulo em conta criada pelo cliente
 * @property string $password
 * @property string $status
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->trashed();
    }

    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** Pode entrar no host atual: cliente (vínculo ativo) ou plataforma. */
    public function canEnterCurrentContext(): bool
    {
        return app(TenantContext::class)->isPlatform()
            ? PlatformAdmin::allows($this)
            : app(AccessResolver::class)->membership($this) !== null;
    }

    /** Só envia o link a quem pode entrar no host do request. */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->canEnterCurrentContext()) {
            $this->notify(new ResetPassword($token));
        }
    }

    /** @return Attribute<string|null, string|null> */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : mb_strtolower(trim($value)));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'mfa_required' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
