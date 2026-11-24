<x-layouts.platform :title="'Campos da agenda · '.$tenant->name">
    <x-slot:actions><a class="text-sm text-brand-700 underline" href="{{ route('platform.tenants.show', $tenant) }}">Voltar ao cliente</a></x-slot:actions>
    @if ($errors->any())
        <x-alert tone="error" class="mb-4">{{ $errors->first() }}</x-alert>
    @endif
    @include('operacao::fields.editor')
</x-layouts.platform>
