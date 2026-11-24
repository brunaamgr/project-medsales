@php
    $access = app(\App\Core\Permissions\AccessResolver::class);
    $modules = array_filter(app(\App\Core\Modules\ModuleRegistry::class)->business(), fn ($m) => $access->hasAnyIn(auth()->user(), $m->slug));
@endphp
<x-layouts.app title="Início">
    @if ($modules === [])
        <x-alert>Nenhum módulo liberado para o seu perfil.</x-alert>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($modules as $module)
                <li>
                    <a href="{{ route($module->slug.'.home') }}" class="block rounded-lg border border-stone-200 bg-white p-5 hover:border-brand-600">
                        <span class="font-medium">{{ $module->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
