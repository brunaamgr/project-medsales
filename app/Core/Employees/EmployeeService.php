<?php

namespace App\Core\Employees;

use App\Core\Audit\AuditLogger;
use App\Core\Auth\SessionRevoker;
use App\Core\Employees\Models\Employee;
use App\Core\Permissions\AccessResolver;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use App\Shared\Support\Cpf;
use App\Shared\Support\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final readonly class EmployeeService
{
    public function __construct(
        private AccessProfiles $profiles,
        private AccessResolver $access,
        private SessionRevoker $sessions,
        private AuditLogger $audit,
    ) {}

    /**
     * Sempre cria conta nova: nunca liga a uma conta existente, que pode ser de outro cliente.
     *
     * @param  array<string, mixed>  $data  já validado
     * @param  Collection<int, Unit>  $units
     */
    public function create(array $data, Profile $profile, Collection $units, User $actor): Membership
    {
        $this->assertAssignable($profile, $actor);

        return Transaction::run(function () use ($data, $profile, $units, $actor): Membership {
            $user = User::query()->create(['name' => $data['name'], 'password' => $data['password']]);
            $membership = new Membership(['user_id' => $user->id, 'status' => 'active', 'joined_at' => now(), 'invited_by' => $actor->id]);
            $membership->login = $data['login'];
            $membership->save();
            $membership->replaceRole($this->profiles->role($profile));
            $membership->replaceUnits($units);
            $this->fill(new Employee(['membership_id' => $membership->id, 'created_by' => $actor->id]), $data);
            $this->audit->record('funcionario.created', $membership, after: ['perfil' => $profile->value]);

            return $membership;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Unit>  $units
     */
    public function update(Membership $target, array $data, ?Profile $profile, Collection $units, User $actor): void
    {
        $this->assertManageable($target, $actor);
        $changesProfile = $profile !== null && $profile !== $this->profiles->of($target);

        if ($changesProfile) {
            $this->assertNotSelf($target, $actor);
            $this->assertAssignable($profile, $actor);
        }

        Transaction::run(function () use ($target, $data, $profile, $changesProfile, $units): void {
            if ($changesProfile && $profile !== Profile::SuperAdm) {
                $this->assertNotLastSuperAdm($target);
            }

            $this->fill(Employee::query()->firstOrNew(['membership_id' => $target->id]), $data);
            $target->login = $data['login'] ?? null;
            $target->save();
            $target->replaceUnits($units);

            // Conta com e-mail próprio pode servir a outro cliente: o nome global dela não muda aqui.
            if ($this->isManaged($target)) {
                $target->user?->update(['name' => $data['name']]);
            }

            if ($changesProfile) {
                $before = $this->profiles->of($target)?->value;
                $target->replaceRole($this->profiles->role($profile));
                $this->revoke($target);
                $this->audit->record('funcionario.profile', $target, ['perfil' => $before], ['perfil' => $profile->value]);
            }

            $this->audit->record('funcionario.updated', $target);
        });

        $this->access->flush();
    }

    public function setStatus(Membership $target, string $status, User $actor): void
    {
        $this->assertManageable($target, $actor);
        $this->assertNotSelf($target, $actor);

        Transaction::run(function () use ($target, $status): void {
            if ($status !== 'active') {
                $this->assertNotLastSuperAdm($target);
            }

            $before = $target->status;
            $target->update(['status' => $status]);
            $this->revoke($target);
            $this->audit->record('funcionario.status', $target, ['status' => $before], ['status' => $status]);
        });

        $this->access->flush();
    }

    public function resetPassword(Membership $target, string $password, User $actor): void
    {
        $this->assertManageable($target, $actor);
        $this->assertNotSelf($target, $actor);

        if (! $this->isManaged($target)) {
            throw ValidationException::withMessages(['password' => 'Esta conta usa e-mail próprio; a pessoa troca a senha por "Esqueci minha senha".']);
        }

        Transaction::run(function () use ($target, $password): void {
            $target->user?->update(['password' => $password]);
            $this->revoke($target);
            $this->audit->record('funcionario.password_reset', $target);
        });
    }

    /** Remove o acesso e os documentos; nome e histórico do que a pessoa fez permanecem. */
    public function remove(Membership $target, User $actor): void
    {
        $this->assertManageable($target, $actor);
        $this->assertNotSelf($target, $actor);

        Transaction::run(function () use ($target): void {
            $this->assertNotLastSuperAdm($target);
            $this->revoke($target);
            $target->login = null;
            $target->status = 'removed';
            $target->save();
            $target->roles()->detach();
            Employee::query()->where('membership_id', $target->id)->first()
                ?->update(['cpf' => null, 'cpf_hash' => null, 'rg' => null, 'phone' => null, 'email' => null]);
            $this->audit->record('funcionario.removed', $target);
        });

        $this->access->flush();
    }

    /** Conta criada pelo próprio cliente; as que têm e-mail vieram de convite e podem atender a outros. */
    public function isManaged(Membership $target): bool
    {
        return $target->user !== null && $target->user->email === null;
    }

    /** @param array<string, mixed> $data */
    private function fill(Employee $employee, array $data): void
    {
        $cpf = Cpf::digits((string) $data['cpf']);

        $employee->fill([
            'name' => $data['name'],
            'cpf' => $cpf,
            'cpf_hash' => Cpf::hash($cpf),
            'rg' => $data['rg'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
        ])->save();
    }

    private function revoke(Membership $target): void
    {
        if ($target->user !== null) {
            $this->sessions->revokeAll($target->user);
        }
    }

    private function assertManageable(Membership $target, User $actor): void
    {
        abort_if($target->status === 'removed', 404);
        abort_unless($this->profiles->canManage($actor, $target), 403);
    }

    private function assertNotSelf(Membership $target, User $actor): void
    {
        abort_if($target->user_id === $actor->id, 403);
    }

    private function assertAssignable(Profile $profile, User $actor): void
    {
        abort_unless(in_array($profile, $this->profiles->assignable($actor), true), 403);
    }

    private function assertNotLastSuperAdm(Membership $target): void
    {
        if ($this->profiles->of($target) !== Profile::SuperAdm) {
            return;
        }

        // Trava as linhas: duas remoções ao mesmo tempo não zeram os Super Adm.
        $others = Membership::query()->where('status', 'active')->whereKeyNot($target->id)->with('roles')->lockForUpdate()->get()
            ->filter(fn (Membership $m): bool => $this->profiles->of($m) === Profile::SuperAdm);

        if ($others->isEmpty()) {
            throw ValidationException::withMessages(['profile' => 'O cliente precisa ter ao menos um Super Adm ativo.']);
        }
    }
}
