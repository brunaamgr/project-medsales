@props(['tone' => 'info'])
<div role="status" {{ $attributes->class(['rounded-md border px-4 py-3 text-sm', 'border-brand-100 bg-brand-50 text-brand-900' => $tone === 'info', 'border-red-200 bg-red-50 text-red-800' => $tone === 'error']) }}>{{ $slot }}</div>
