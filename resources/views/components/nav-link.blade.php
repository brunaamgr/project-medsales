@props(['href', 'active' => false])
<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class(['rounded-md px-3 py-2 hover:bg-brand-700', 'bg-brand-700 font-medium text-white' => $active]) }}>{{ $slot }}</a>
