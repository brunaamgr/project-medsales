@props(['status'])
<x-badge :tone="$status === 'active' ? 'success' : 'danger'">{{ $status === 'active' ? 'ativo' : 'suspenso' }}</x-badge>
