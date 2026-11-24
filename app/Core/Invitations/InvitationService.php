<?php

namespace App\Core\Invitations;

use App\Core\Audit\AuditLogger;
use App\Core\Invitations\Models\Invitation;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantUrl;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Sempre no contexto do tenant do convite. */
final readonly class InvitationService
{
    private const DAYS = 7;

    public function __construct(private TenantContext $context, private AuditLogger $audit) {}

    /** Um convite novo cancela os anteriores em aberto para o mesmo e-mail. */
    public function issue(string $email, Role $role, User $actor): IssuedInvitation
    {
        $email = mb_strtolower(trim($email));
        $token = Str::random(64);

        Invitation::open()->where('email', $email)->update(['revoked_at' => now()]);

        $invitation = Invitation::query()->create([
            'email' => $email,
            'role_id' => $role->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::DAYS),
            'created_by' => $actor->id,
        ]);

        $this->audit->record('invitation.created', $invitation, after: ['role' => $role->slug]);

        $tenant = $this->context->current();
        assert($tenant !== null);

        return new IssuedInvitation($invitation, TenantUrl::to($tenant, "convite/{$token}"));
    }

    /** Depois do commit. Falha de SMTP não desfaz o cadastro; dá para reenviar. */
    public function send(IssuedInvitation $issued, string $tenantName): bool
    {
        try {
            Notification::route('mail', $issued->invitation->email)->notify(new InvitationNotification($tenantName, $issued->url));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function find(string $token): ?Invitation
    {
        $invitation = Invitation::query()->where('token_hash', hash('sha256', $token))->first();

        return $invitation?->isPending() === true ? $invitation : null;
    }

    /** Quem já tem conta confirma a senha atual; o convite nunca troca a senha de ninguém. */
    public function accept(string $token, ?string $name, string $password): void
    {
        DB::transaction(function () use ($token, $name, $password): void {
            $invitation = Invitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if ($invitation?->isPending() !== true) {
                throw ValidationException::withMessages(['password' => 'Convite inválido ou expirado.']);
            }

            $user = User::query()->where('email', $invitation->email)->first();

            if ($user !== null && (! $user->isActive() || ! Hash::check($password, $user->password))) {
                throw ValidationException::withMessages(['password' => 'Senha incorreta.']);
            }

            $user ??= User::query()->create(['name' => (string) $name, 'email' => $invitation->email, 'password' => $password]);
            $membership = Membership::query()->where('user_id', $user->id)->first();

            if ($membership?->status === 'suspended') {
                throw ValidationException::withMessages(['password' => 'Seu acesso está suspenso. Fale com a administração.']);
            }

            $membership ??= new Membership(['user_id' => $user->id]);
            $membership->fill(['status' => 'active', 'joined_at' => now()])->save();
            $membership->assignRole($invitation->role);

            // Com uma única unidade não há o que escolher.
            $units = Unit::query()->where('status', 'active')->limit(2)->get();
            if ($units->count() === 1) {
                $membership->assignUnit($units->sole());
            }

            $invitation->update(['accepted_at' => now()]);

            $this->audit->record('invitation.accepted', $invitation, actorId: $user->id);
        });
    }
}
