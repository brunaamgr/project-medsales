@props(['href', 'title', 'text'])
<a href="{{ $href }}" class="block h-full rounded-lg border border-stone-200 bg-white p-5 hover:border-brand-600">
    <span class="font-medium">{{ $title }}</span>
    <span class="mt-1 block text-sm text-stone-600">{{ $text }}</span>
</a>
