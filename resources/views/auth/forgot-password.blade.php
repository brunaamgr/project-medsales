<x-layouts.guest title="Recuperar senha">
    <p class="mb-4 text-sm text-stone-600">Informe seu e-mail. Se houver uma conta ativa, enviaremos um link.</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-field name="email" label="E-mail" type="email" autocomplete="username" required autofocus />
        <x-button class="w-full">Enviar link</x-button>
    </form>
    <a href="{{ route('login') }}" class="mt-4 text-sm text-brand-700 underline">Voltar ao login</a>
</x-layouts.guest>
