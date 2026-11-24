@props(['title'])
<x-layouts.base :title="$title">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:bg-white focus:p-2">Pular para o conteúdo</a>

    <div x-data="toggle" class="md:flex">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-4 py-3 md:hidden">
            <span class="font-semibold">MedSales · Plataforma</span>
            <button type="button" x-on:click="toggle" x-bind:aria-expanded="open" aria-controls="menu" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm">Menu</button>
        </header>

        <nav id="menu" aria-label="Principal" x-bind:class="open ? 'flex!' : ''" class="hidden flex-col gap-1 border-b border-stone-200 bg-stone-900 p-4 text-sm text-stone-100 md:flex md:min-h-screen md:w-60 md:shrink-0 md:border-0">
            <p class="hidden px-3 pb-4 text-base font-semibold text-white md:block">MedSales · Plataforma</p>
            <x-nav-link :href="route('platform.dashboard')" :active="request()->routeIs('platform.dashboard')">Visão geral</x-nav-link>
            <x-nav-link :href="route('platform.tenants.index')" :active="request()->routeIs('platform.tenants.*')">Clientes</x-nav-link>
            <x-nav-link :href="route('platform.audit')" :active="request()->routeIs('platform.audit')">Auditoria</x-nav-link>
            <form method="POST" action="{{ route('logout') }}" class="mt-4 border-t border-stone-700 pt-4 md:mt-auto">
                @csrf
                <p class="truncate px-3 pb-2 text-stone-300">{{ auth()->user()->name }}</p>
                <button class="w-full rounded-md px-3 py-2 text-left hover:bg-brand-700">Sair</button>
            </form>
        </nav>

        <main id="conteudo" class="min-w-0 flex-1 p-4 md:p-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ $title }}</h1>
                {{ $actions ?? '' }}
            </div>

            @if (session('status'))
                <x-alert class="mb-6">{{ session('status') }}</x-alert>
            @endif
            @if (session('invite_url'))
                <x-alert class="mb-6">
                    Ambiente local: o e-mail vai só para o log. Link do convite:
                    <a class="block break-all underline" href="{{ session('invite_url') }}">{{ session('invite_url') }}</a>
                </x-alert>
            @endif

            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
