<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table {{ $attributes->class('w-full text-left text-sm [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3 [&_th]:font-medium [&_th]:text-stone-600 [&_tbody_tr]:border-t [&_tbody_tr]:border-stone-100') }}>{{ $slot }}</table>
</div>
