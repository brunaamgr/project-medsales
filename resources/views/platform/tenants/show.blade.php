<x-layouts.platform :title="$tenant->name">
    <x-slot:actions><x-tenant-status :status="$tenant->status" /></x-slot:actions>

    <div class="space-y-6">
        <x-card title="Endereço">
            <a class="break-all text-brand-700 underline" href="{{ $url }}">{{ $url }}</a>
        </x-card>

        <x-card title="Módulos e funcionalidades">
            <form method="POST" action="{{ route('platform.tenants.entitlements', $tenant) }}">
                @csrf @method('PUT')
                <x-entitlement-fields :modules="$modules" :enabled-modules="$enabled['modules']" :enabled-features="$enabled['features']" />
                <x-button class="mt-4">Salvar</x-button>
            </form>
            @foreach ($enabled['modules'] as $slug)
                @if (Route::has("platform.modules.{$slug}.settings"))
                    <p class="mt-4"><a class="text-brand-700 underline" href="{{ route("platform.modules.{$slug}.settings", $tenant) }}">Personalizar campos de {{ $modules[$slug]->name }}</a></p>
                @endif
            @endforeach
        </x-card>

        <x-card title="Pessoas com acesso">
            <x-table>
                <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Vínculo</th></tr></thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr><td>{{ $member->user->name }}</td><td>{{ $member->user->email ?? 'login próprio' }}</td><td>{{ $member->roles->pluck('name')->join(', ') ?: '—' }}</td><td>{{ ['active' => 'ativo', 'suspended' => 'suspenso', 'invited' => 'convidado'][$member->status] ?? $member->status }}</td></tr>
                    @empty
                        <tr><td colspan="4">Ninguém aceitou convite ainda.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>

        <x-card title="Convites de responsável">
            <ul class="mb-4 divide-y divide-stone-100 text-sm">
                @forelse ($invitations as $invitation)
                    <li class="flex flex-wrap justify-between gap-2 py-2"><span>{{ $invitation->email }}</span><x-badge>{{ $invitation->statusLabel() }}</x-badge></li>
                @empty
                    <li>Nenhum convite.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('platform.tenants.invite', $tenant) }}" class="flex max-w-lg flex-wrap items-end gap-3">
                @csrf
                <div class="grow"><x-field name="email" label="Convidar ou reenviar para" type="email" required /></div>
                <x-button>Enviar convite</x-button>
            </form>
            <p class="mt-2 text-sm text-stone-600">Reenviar cancela o convite anterior.</p>
        </x-card>

        <x-card :title="$tenant->status === 'active' ? 'Suspender cliente' : 'Reativar cliente'">
            <form method="POST" action="{{ route('platform.tenants.status', $tenant) }}" class="flex max-w-lg flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'suspended' : 'active' }}">
                <div class="grow"><x-field name="reason" label="Motivo" required maxlength="255" /></div>
                <x-button>{{ $tenant->status === 'active' ? 'Suspender acesso' : 'Reativar acesso' }}</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.platform>
