<?php

namespace App\Core\Platform\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Invitations\InvitationService;
use App\Core\Invitations\IssuedInvitation;
use App\Core\Invitations\Models\Invitation;
use App\Core\Modules\ModuleRegistry;
use App\Core\Platform\ProvisionTenant;
use App\Core\Platform\SyncEntitlements;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Subdomain;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantUrl;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class TenantController
{
    public function __construct(
        private TenantContext $context,
        private ModuleRegistry $registry,
        private AuditLogger $audit,
        private InvitationService $invitations,
    ) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('busca')->toString());

        return view('platform.tenants.index', [
            'search' => $search,
            'tenants' => Tenant::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('name', "%{$search}%")->orWhereLike('subdomain', "%{$search}%")))
                ->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('platform.tenants.create', [
            'modules' => $this->registry->business(),
            'baseDomain' => config()->string('tenancy.base_domain'),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $value = mb_strtolower(trim($request->string('valor')->toString()));

        return response()->json(['message' => match (true) {
            $value === '' => '',
            ! Subdomain::isValid($value) => 'Inválido ou reservado.',
            $this->taken($value) => 'Já está em uso.',
            default => 'Disponível.',
        }]);
    }

    public function store(Request $request, ProvisionTenant $provision): RedirectResponse
    {
        $request->merge(['subdomain' => mb_strtolower(trim($request->string('subdomain')->toString()))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Subdomain::isValid((string) $value)) {
                    $fail('Use de 3 a 63 letras minúsculas, números ou hífen. Palavras reservadas não são aceitas.');
                } elseif ($this->taken((string) $value)) {
                    $fail('Este subdomínio já está em uso.');
                }
            }],
            'owner_email' => ['required', 'email:rfc', 'max:255'],
            ...$this->entitlementRules(),
        ]);

        [$tenant, $issued] = $provision->handle(
            $data['name'], $data['subdomain'], $data['owner_email'],
            array_values($data['modules'] ?? []), array_values($data['features'] ?? []), $this->actor($request),
        );

        return $this->deliver($tenant, $issued, 'Cliente cadastrado.');
    }

    public function show(Tenant $tenant, SyncEntitlements $entitlements): View
    {
        [$members, $invitations, $enabled] = $this->context->run($tenant, fn (): array => [
            Membership::query()->with(['user', 'roles'])->oldest('id')->get(),
            Invitation::query()->with('role')->latest('id')->limit(20)->get(),
            $entitlements->snapshot(),
        ]);

        return view('platform.tenants.show', [
            'tenant' => $tenant,
            'url' => TenantUrl::to($tenant),
            'modules' => $this->registry->business(),
            'members' => $members,
            'invitations' => $invitations,
            'enabled' => $enabled,
        ]);
    }

    public function entitlements(Request $request, Tenant $tenant, SyncEntitlements $sync): RedirectResponse
    {
        $data = $request->validate($this->entitlementRules());
        $sync->handle($tenant, array_values($data['modules'] ?? []), array_values($data['features'] ?? []));

        return back()->with('status', 'Módulos e funcionalidades atualizados.');
    }

    public function status(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $before = $tenant->status;
        $tenant->update(['status' => $data['status']]);
        $this->audit->record('platform.tenant.status', $tenant, ['status' => $before], ['status' => $data['status'], 'reason' => $data['reason']]);

        return back()->with('status', $data['status'] === 'active' ? 'Cliente reativado.' : 'Cliente suspenso. O acesso foi bloqueado.');
    }

    public function invite(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $issued = $this->context->run($tenant, fn (): IssuedInvitation => $this->invitations->issue($data['email'], Role::system('tenant_owner'), $this->actor($request)));
        $this->audit->record('platform.tenant.owner_invited', $tenant);

        return $this->deliver($tenant, $issued, 'Convite gerado.');
    }

    private function deliver(Tenant $tenant, IssuedInvitation $issued, string $message): RedirectResponse
    {
        $sent = $this->invitations->send($issued, $tenant->name);
        $redirect = redirect()->route('platform.tenants.show', $tenant)
            ->with('status', $message.($sent ? ' E-mail de convite enviado.' : ' O e-mail não saiu; gere um novo convite.'));

        // Sem SMTP local, o link aparece na tela. Nunca fora do ambiente local.
        return app()->isLocal() ? $redirect->with('invite_url', $issued->url) : $redirect;
    }

    /** @return array<string, list<mixed>> */
    private function entitlementRules(): array
    {
        $features = array_merge(...array_values(array_map(fn ($m): array => array_keys(array_filter($m->features, fn (array $f): bool => $f['available'])), $this->registry->business())));

        return [
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(array_keys($this->registry->business()))],
            'features' => ['array'],
            'features.*' => ['string', Rule::in($features)],
        ];
    }

    private function taken(string $subdomain): bool
    {
        return Tenant::withTrashed()->where(fn ($q) => $q->where('subdomain', $subdomain)->orWhere('slug', $subdomain))->exists();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(403);
    }
}
