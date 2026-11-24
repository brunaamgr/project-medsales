<x-layouts.app title="Minha senha">
    <x-flash />
    <form method="POST" action="{{ route('own-password.update') }}" class="max-w-md space-y-4">
        @csrf @method('PUT')
        <x-field name="current_password" label="Senha atual" type="password" autocomplete="current-password" required />
        <x-field name="password" label="Nova senha (mínimo de 12 caracteres)" type="password" autocomplete="new-password" required />
        <x-field name="password_confirmation" label="Confirme a nova senha" type="password" autocomplete="new-password" required />
        <x-button>Trocar senha</x-button>
    </form>
</x-layouts.app>
