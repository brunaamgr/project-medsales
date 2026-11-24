@props(['title'])
@php($context = app(\App\Core\Tenancy\TenantContext::class))
<x-layouts.base :title="$title">
    <main class="mx-auto flex min-h-screen w-full max-w-sm flex-col justify-center px-4 py-10">
        <p class="text-sm font-medium text-brand-700">{{ $context->isPlatform() ? 'Administração da plataforma' : $context->current()?->name }}</p>
        <h1 class="mt-1 mb-6 text-2xl font-semibold">{{ $title }}</h1>

        @if (session('status'))
            <x-alert class="mb-4">{{ session('status') }}</x-alert>
        @endif

        {{ $slot }}
    </main>
</x-layouts.base>
