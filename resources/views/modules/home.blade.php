@php($entitlements = app(\App\Core\Modules\Entitlements::class))
<x-layouts.app :title="$module->name">
    <x-alert class="mb-6">Módulo em construção. Estas são as funcionalidades contratadas.</x-alert>
    <ul class="flex flex-wrap gap-2">
        @foreach ($module->features as $slug => $feature)
            @if ($entitlements->feature($slug))
                <li><x-badge tone="success">{{ $feature['name'] }}</x-badge></li>
            @endif
        @endforeach
    </ul>
</x-layouts.app>
