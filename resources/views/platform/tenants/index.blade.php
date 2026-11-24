<x-layouts.platform title="Clientes">
    <x-slot:actions><x-link-button :href="route('platform.tenants.create')">Novo cliente</x-link-button></x-slot:actions>

    <form method="GET" class="mb-4 flex max-w-md gap-2">
        <input type="search" name="busca" value="{{ $search }}" aria-label="Buscar cliente" placeholder="Nome ou subdomínio" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm">
        <x-button>Buscar</x-button>
    </form>

    <x-table>
        <thead><tr><th>Cliente</th><th>Endereço</th><th>Status</th><th>Cadastro</th></tr></thead>
        <tbody>
            @forelse ($tenants as $tenant)
                <tr>
                    <td><a class="text-brand-700 underline" href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->name }}</a></td>
                    <td>{{ $tenant->subdomain }}.{{ config('tenancy.base_domain') }}</td>
                    <td><x-tenant-status :status="$tenant->status" /></td>
                    <td class="whitespace-nowrap">{{ \App\Shared\Support\LocalTime::format($tenant->created_at, 'd/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhum cliente encontrado.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div class="mt-4">{{ $tenants->links('pagination::simple-tailwind') }}</div>
</x-layouts.platform>
