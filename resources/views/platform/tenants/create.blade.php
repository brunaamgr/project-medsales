<x-layouts.platform title="Novo cliente">
    <form method="POST" action="{{ route('platform.tenants.store') }}" class="space-y-6">
        @csrf
        <x-card title="Dados do cliente">
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="name" label="Nome" required />
                <x-field name="owner_email" label="E-mail da pessoa responsável" type="email" required />
                <div x-data="subdomain" data-url="{{ route('platform.tenants.availability') }}">
                    <x-field name="subdomain" label="Subdomínio" required autocomplete="off" x-on:input.debounce.400ms="check" />
                    <p class="mt-1 text-sm text-stone-600">Endereço: <em>subdomínio</em>.{{ $baseDomain }}. <span x-text="message" aria-live="polite" class="font-medium"></span></p>
                </div>
            </div>
        </x-card>
        <x-card title="Módulos e funcionalidades">
            <x-entitlement-fields :modules="$modules" :enabled-modules="old('modules', [])" :enabled-features="old('features', collect($modules)->flatMap(fn ($m) => array_keys(array_filter($m->features, fn ($f) => $f['default'])))->all())" />
        </x-card>
        <x-button>Cadastrar e enviar convite</x-button>
    </form>
</x-layouts.platform>
