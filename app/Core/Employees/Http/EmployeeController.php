<?php

namespace App\Core\Employees\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Employees\AccessProfiles;
use App\Core\Employees\EmployeeService;
use App\Core\Employees\Models\Employee;
use App\Core\Employees\Profile;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use App\Shared\Support\Cpf;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class EmployeeController
{
    public const LOGIN = '/^[a-z0-9][a-z0-9._-]{2,39}$/D';

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly AccessProfiles $profiles,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('busca')->toString());
        $like = '%'.addcslashes($search, '%_\\').'%';

        return view('employees.index', [
            'search' => $search,
            'profiles' => $this->profiles,
            'actor' => $this->actor($request),
            'members' => Membership::query()->with(['user', 'roles', 'employee'])
                ->whereIn('status', ['active', 'suspended'])
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->whereLike('login', $like)
                    ->orWhereHas('employee', fn ($e) => $e->whereLike('name', $like)->orWhereLike('job_title', $like)->orWhereLike('department', $like))
                    ->orWhereHas('user', fn ($u) => $u->whereLike('name', $like))))
                ->orderBy('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, Membership $member): View
    {
        abort_if($member->status === 'removed', 404);
        abort_unless($this->profiles->canManage($this->actor($request), $member), 403);
        // A ficha mostra CPF e RG: abrir fica registrado.
        $this->audit->record('funcionario.viewed', $member);

        return $this->form($request, $member);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $this->employees->create($data, Profile::from($data['profile']), $this->units($data), $this->actor($request));

        return redirect()->route('employees.index')->with('status', 'Funcionário cadastrado. Já pode entrar com o login e a senha informados.');
    }

    public function update(Request $request, Membership $member): RedirectResponse
    {
        // Antes de validar: quem não pode editar não recebe pistas.
        abort_if($member->status === 'removed', 404);
        abort_unless($this->profiles->canManage($this->actor($request), $member), 403);

        $data = $this->validated($request, $member);
        $profile = filled($data['profile'] ?? null) ? Profile::from($data['profile']) : null;
        $this->employees->update($member, $data, $profile, $this->units($data), $this->actor($request));

        return redirect()->route('employees.index')->with('status', 'Cadastro atualizado.');
    }

    public function status(Request $request, Membership $member): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $this->employees->setStatus($member, $data['status'], $this->actor($request));

        return redirect()->route('employees.index')->with('status', $data['status'] === 'active' ? 'Acesso reativado.' : 'Acesso suspenso.');
    }

    public function password(Request $request, Membership $member): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:200', Password::default(), 'confirmed']]);
        $this->employees->resetPassword($member, $data['password'], $this->actor($request));

        return redirect()->route('employees.index')->with('status', 'Senha alterada. As sessões abertas da pessoa foram encerradas.');
    }

    public function destroy(Request $request, Membership $member): RedirectResponse
    {
        $this->employees->remove($member, $this->actor($request));

        return redirect()->route('employees.index')->with('status', 'Funcionário excluído. O histórico do que ele fez foi mantido.');
    }

    private function form(Request $request, ?Membership $member): View
    {
        $actor = $this->actor($request);

        return view('employees.form', [
            'member' => $member,
            'employee' => $member?->employee,
            'self' => $member !== null && $member->user_id === $actor->id,
            'managed' => $member === null || $this->employees->isManaged($member),
            'assignable' => $this->profiles->assignable($actor),
            'current' => $member === null ? null : $this->profiles->of($member),
            'currentLabel' => $member === null ? null : $this->profiles->label($member),
            'units' => Unit::query()->where('status', 'active')->orderBy('name')->get(),
            'memberUnits' => $member?->units->modelKeys() ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Membership $member): array
    {
        $request->merge(['login' => Str::lower(trim($request->string('login')->toString())) ?: null]);
        $units = Unit::query()->where('status', 'active')->pluck('public_id')->all();
        $profiles = array_map(fn (Profile $p): string => $p->value, $this->profiles->assignable($this->actor($request)));
        $managed = $member === null || $this->employees->isManaged($member);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:20', $this->cpfRule($member)],
            'rg' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'login' => [$managed ? 'required' : 'nullable', 'string', 'regex:'.self::LOGIN, $this->loginRule($member)],
            'profile' => [$member === null ? 'required' : 'nullable', Rule::in([...$profiles, ...($member === null ? [] : [$this->profiles->of($member)?->value])])],
            'units' => [count($units) > 1 ? 'required' : 'nullable', 'array'],
            'units.*' => [Rule::in($units)],
            ...($member === null ? ['password' => ['required', 'string', 'max:200', Password::default(), 'confirmed']] : []),
        ], [
            'login.regex' => 'Use de 3 a 40 letras minúsculas, números, ponto, hífen ou sublinhado.',
            'units.required' => 'Escolha ao menos uma unidade.',
        ], ['name' => 'nome', 'cpf' => 'CPF', 'login' => 'login', 'profile' => 'perfil de acesso', 'phone' => 'telefone', 'job_title' => 'cargo', 'department' => 'setor', 'rg' => 'RG']);
    }

    private function cpfRule(?Membership $member): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($member): void {
            if (! Cpf::isValid((string) $value)) {
                $fail('Informe um CPF válido.');
            } elseif (Employee::query()->where('cpf_hash', Cpf::hash((string) $value))->when($member, fn ($q) => $q->where('membership_id', '!=', $member->id))->exists()) {
                $fail('Já existe um funcionário com este CPF.');
            }
        };
    }

    private function loginRule(?Membership $member): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($member): void {
            if (Membership::query()->where('login', $value)->when($member, fn ($q) => $q->whereKeyNot($member->id))->exists()) {
                $fail('Este login já está em uso.');
            }
        };
    }

    /**
     * Com uma única unidade não há o que escolher; sem nenhuma, cria a principal.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Unit>
     */
    private function units(array $data): Collection
    {
        $units = Unit::query()->where('status', 'active')->get();

        if ($units->isEmpty()) {
            return Unit::query()->whereKey(Unit::query()->create(['name' => 'Principal'])->id)->get();
        }

        return $units->count() === 1 ? $units : $units->whereIn('public_id', $data['units'] ?? [])->values();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(403);
    }
}
