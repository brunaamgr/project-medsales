<x-layouts.app title="Pacientes">
    <x-flash />
    <form method="GET" class="mb-4 flex max-w-md gap-2">
        <input type="search" name="busca" value="{{ $search }}" aria-label="Buscar paciente" placeholder="Nome ou telefone" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm">
        <x-button>Buscar</x-button>
    </form>
    <x-table>
        <thead><tr><th>Nome</th><th>Telefone</th></tr></thead>
        <tbody>
            @forelse ($patients as $patient)
                <tr>
                    <td>
                        @can('operacao.paciente.update', $patient)
                            <a class="text-brand-700 underline" href="{{ route('operacao.pacientes.edit', $patient) }}">{{ $patient->name }}</a>
                        @else
                            {{ $patient->name }}
                        @endcan
                    </td>
                    <td>{{ $patient->phone ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Nenhum paciente encontrado.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div class="mt-4">{{ $patients->links('pagination::simple-tailwind') }}</div>
</x-layouts.app>
