@props(['modules', 'enabledModules' => [], 'enabledFeatures' => []])
<div class="grid gap-4 lg:grid-cols-2">
    @foreach ($modules as $slug => $module)
        <fieldset class="rounded-md border border-stone-200 p-4">
            <legend class="px-1">
                <label class="flex items-center gap-2 font-medium">
                    <input type="checkbox" name="modules[]" value="{{ $slug }}" @checked(in_array($slug, $enabledModules, true))>
                    {{ $module->name }}
                </label>
            </legend>
            <div class="grid grid-cols-2 gap-2 text-sm">
                @foreach ($module->features as $feature => $meta)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="features[]" value="{{ $feature }}" @checked(in_array($feature, $enabledFeatures, true)) @disabled(! $meta['available'])>
                        <span @class(['text-stone-500' => ! $meta['available']])>{{ $meta['name'] }}{{ $meta['available'] ? '' : ' (em breve)' }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
<p class="mt-3 text-sm text-stone-600">A funcionalidade só vale com o módulo marcado.</p>
