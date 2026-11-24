<x-layouts.guest title="Entrar">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-field name="login" label="Login ou e-mail" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
        <x-field name="password" label="Senha" type="password" autocomplete="current-password" required />
        <x-button class="w-full">Entrar</x-button>
    </form>
    <a href="{{ route('password.request') }}" class="mt-4 text-sm text-brand-700 underline">Esqueci minha senha</a>
</x-layouts.guest>
