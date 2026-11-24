<x-layouts.app title="Agenda">
    <x-flash />
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <label>De<input type="date" name="de" value="{{ $from }}" class="block rounded-md border border-stone-300 bg-white px-3 py-2"></label>
        <label>Até<input type="date" name="ate" value="{{ $to }}" class="block rounded-md border border-stone-300 bg-white px-3 py-2"></label>
        <label>Status
            <select name="status" class="block rounded-md border border-stone-300 bg-white px-3 py-2">
                <option value="">Todos, menos cancelados</option>
                @foreach (\App\Modules\Operacao\Models\Appointment::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($professionals->isNotEmpty())
            <label>Profissional
                <select name="profissional" class="block rounded-md border border-stone-300 bg-white px-3 py-2">
                    <option value="">Todos</option>
                    @foreach ($professionals as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['profissional'] ?? '') == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <x-button>Filtrar</x-button>
        @can('operacao.agenda.create')
            <x-link-button :href="route('operacao.agenda.create')" class="ml-auto">Nova consulta</x-link-button>
        @endcan
    </form>

    <x-table>
        <thead><tr><th>Data</th><th>Hora</th><th>Paciente</th><th>Profissional</th><th>Status</th><th>Código</th></tr></thead>
        <tbody>
            @forelse ($appointments as $appointment)
                <tr>
                    <td class="whitespace-nowrap">{{ $appointment->date->format('d/m/Y') }}</td>
                    <td>{{ $appointment->time() ?? '—' }}</td>
                    <td>
                        @can('operacao.agenda.view', $appointment)
                            <a class="text-brand-700 underline" href="{{ route('operacao.agenda.edit', $appointment) }}">{{ $appointment->patient->name }}</a>
                        @else
                            {{ $appointment->patient->name }}
                        @endcan
                    </td>
                    <td>{{ $appointment->professional?->name ?? '—' }}</td>
                    <td><x-badge :tone="match ($appointment->status) { 'done', 'confirmed' => 'success', 'cancelled', 'no_show' => 'danger', default => 'neutral' }">{{ \App\Modules\Operacao\Models\Appointment::STATUSES[$appointment->status] }}</x-badge></td>
                    <td>{{ $appointment->number }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Nenhuma consulta no período.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div class="mt-4">{{ $appointments->links('pagination::simple-tailwind') }}</div>
</x-layouts.app>
