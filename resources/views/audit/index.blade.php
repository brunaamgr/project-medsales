<x-layouts.app title="Auditoria">
    <x-table>
        <thead><tr><th>Quando</th><th>Ação</th><th>Usuário</th><th>IP</th><th>Resultado</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap">{{ \App\Shared\Support\LocalTime::format($log->created_at) }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->actor_id ? '#'.$log->actor_id : '—' }}</td>
                    <td>{{ $log->ip ?? '—' }}</td>
                    <td><x-badge :tone="$log->result === 'success' ? 'success' : 'danger'">{{ $log->result }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="5">Nenhum registro.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
