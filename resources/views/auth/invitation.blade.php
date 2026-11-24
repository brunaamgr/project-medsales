<x-layouts.guest title="Aceitar convite">
    <p class="mb-4 text-sm text-stone-600">Convite para <strong>{{ $email }}</strong>.</p>
    <form method="POST" action="{{ route('invitation.store', $token) }}" class="space-y-4">
        @csrf
        @if ($existing)
            <p class="text-sm text-stone-600">Você já tem conta. Confirme sua senha atual.</p>
            <x-field name="password" label="Senha" type="password" autocomplete="current-password" required />
        @else
            <x-field name="name" label="Nome completo" autocomplete="name" required />
            <x-field name="password" label="Senha (mínimo de 12 caracteres)" type="password" autocomplete="new-password" required />
            <x-field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
        @endif
        <x-button class="w-full">Aceitar convite</x-button>
    </form>
</x-layouts.guest>
