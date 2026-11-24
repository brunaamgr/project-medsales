<x-layouts.guest title="Nova senha">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" label="E-mail" type="email" :value="$request->email" autocomplete="username" required />
        <x-field name="password" label="Nova senha (mínimo de 12 caracteres)" type="password" autocomplete="new-password" required />
        <x-field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
        <x-button class="w-full">Salvar senha</x-button>
    </form>
</x-layouts.guest>
