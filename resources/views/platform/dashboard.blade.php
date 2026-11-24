<x-layouts.platform title="Visão geral">
    <x-slot:actions><x-link-button :href="route('platform.tenants.create')">Novo cliente</x-link-button></x-slot:actions>

    <dl class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-card><dt class="text-sm text-stone-600">Clientes ativos</dt><dd class="text-3xl font-semibold">{{ $active }}</dd></x-card>
        <x-card><dt class="text-sm text-stone-600">Clientes suspensos</dt><dd class="text-3xl font-semibold">{{ $suspended }}</dd></x-card>
    </dl>

    <x-card title="Últimos cadastros">
        <ul class="divide-y divide-stone-100">
            @forelse ($recent as $tenant)
                <li class="flex items-center justify-between py-2">
                    <a class="text-brand-700 underline" href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                    <x-tenant-status :status="$tenant->status" />
                </li>
            @empty
                <li>Nenhum cliente cadastrado.</li>
            @endforelse
        </ul>
    </x-card>
</x-layouts.platform>
