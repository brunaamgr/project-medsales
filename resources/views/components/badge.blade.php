@props(['tone' => 'neutral'])
<span {{ $attributes->class(['inline-block rounded-full px-2 py-0.5 text-xs font-medium', 'bg-stone-200 text-stone-700' => $tone === 'neutral', 'bg-brand-100 text-brand-900' => $tone === 'success', 'bg-red-100 text-red-800' => $tone === 'danger']) }}>{{ $slot }}</span>
