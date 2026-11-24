<x-layouts.app title="Equipe">
    <x-flash />
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <form method="GET" class="flex max-w-md grow gap-2">
            <input type="search" name="busca" value="{{ $search }}" aria-label="Buscar funcionário" placeholder="Nome, login, cargo ou setor" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm">
            <x-button>Buscar</x-button>
        </form>
        @can('core.role.manage')
            <a href="{{ route('profiles.edit') }}" class="text-sm text-brand-700 underline">Configurar perfis</a>
        @endcan
        @can('core.usuario.create')
            <x-link-button :href="route('employees.create')">Novo funcionário</x-link-button>
        @endcan
    </div>

    <x-table>
        <thead><tr><th>Nome</th><th>Login</th><th>CPF</th><th>Cargo / setor</th><th>Perfil</th><th>Acesso</th></tr></thead>
        <tbody>
            @forelse ($members as $member)
                @php($name = $member->employee?->name ?? $member->user?->name)
                <tr>
                    <td>
                        @if ($actor->can('core.usuario.update') && $profiles->canManage($actor, $member))
                            <a class="text-brand-700 underline" href="{{ route('employees.edit', $member) }}">{{ $name }}</a>
                        @else
                            {{ $name }}
                        @endif
                        @if (! $member->employee) <span class="block text-xs text-stone-600">Cadastro incompleto</span> @endif
                    </td>
                    <td>{{ $member->login ?? $member->user?->email ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ \App\Shared\Support\Cpf::mask($member->employee?->cpf) }}</td>
                    <td>{{ collect([$member->employee?->job_title, $member->employee?->department])->filter()->join(' / ') ?: '—' }}</td>
                    <td>{{ $profiles->label($member) }}</td>
                    <td><x-tenant-status :status="$member->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6">Nenhum funcionário encontrado.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div class="mt-4">{{ $members->links('pagination::simple-tailwind') }}</div>
</x-layouts.app>
