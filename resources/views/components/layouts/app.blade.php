@props(['title'])
<x-layouts.base :title="$title">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:bg-white focus:p-2">Pular para o conteúdo</a>

    <div x-data="toggle" class="md:flex">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-4 py-3 md:hidden">
            <span class="font-semibold">{{ $tenant?->name }}</span>
            <button type="button" x-on:click="toggle" x-bind:aria-expanded="open" aria-controls="menu" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm">Menu</button>
        </header>

        <nav id="menu" aria-label="Principal" x-bind:class="open ? 'flex!' : ''" class="hidden flex-col gap-1 border-b border-stone-200 bg-brand-900 p-4 text-sm text-brand-50 md:flex md:min-h-screen md:w-60 md:shrink-0 md:border-0">
            <p class="hidden px-3 pb-4 text-base font-semibold text-white md:block">{{ $tenant?->name }}</p>
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Início</x-nav-link>
            @foreach ($menu as $module)
                <x-nav-link :href="route($module->slug.'.home')" :active="request()->routeIs($module->slug.'.*')">{{ $module->name }}</x-nav-link>
            @endforeach
            @can('core.usuario.view')
                <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*', 'profiles.*')">Equipe</x-nav-link>
            @endcan
            @can('core.audit.view')
                <x-nav-link :href="route('audit.index')" :active="request()->routeIs('audit.*')">Auditoria</x-nav-link>
            @endcan
            <form method="POST" action="{{ route('logout') }}" class="mt-4 border-t border-brand-700 pt-4 md:mt-auto">
                @csrf
                <p class="truncate px-3 pb-2 text-brand-100">{{ auth()->user()->name }}</p>
                <a href="{{ route('own-password.edit') }}" class="block rounded-md px-3 py-2 hover:bg-brand-700">Minha senha</a>
                <button class="w-full rounded-md px-3 py-2 text-left hover:bg-brand-700">Sair</button>
            </form>
        </nav>

        <main id="conteudo" class="min-w-0 flex-1 p-4 md:p-8">
            <h1 class="mb-6 text-2xl font-semibold">{{ $title }}</h1>
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
