@props(['code', 'title', 'message'])
<x-layouts.base :title="$title">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4">
        <p class="text-sm font-medium text-brand-700">Erro {{ $code }}</p>
        <h1 class="mt-1 text-2xl font-semibold">{{ $title }}</h1>
        <p class="mt-3 text-stone-600">{{ $message }}</p>
        <a href="/" class="mt-6 text-sm text-brand-700 underline">Ir para o início</a>
    </main>
</x-layouts.base>
