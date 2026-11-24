@php
    $id = 'campo-'.$field->key;
    $raw = old($field->path(), $value);
    $current = $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : $raw;
    $class = 'w-full rounded-md border bg-white px-3 py-2 text-sm '.($errors->has($field->path()) ? 'border-red-500' : 'border-stone-300');
@endphp
<div @class(['md:col-span-2' => $field->type === 'textarea'])>
    @if ($field->type === 'checkbox')
        <input type="hidden" name="{{ $field->inputName() }}" value="0">
        <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="{{ $field->inputName() }}" value="1" @checked($current)> {{ $field->label }}</label>
    @else
        <label for="{{ $id }}" class="mb-1 block text-sm font-medium">{{ $field->label }}@if ($field->required) <span aria-hidden="true">*</span>@endif</label>
        @if (in_array($field->type, ['select', 'catalog'], true))
            <select id="{{ $id }}" name="{{ $field->inputName() }}" @required($field->required) class="{{ $class }}">
                <option value="">Selecione</option>
                @foreach ($options as $key => $label)
                    <option value="{{ $key }}" @selected((string) $current === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
        @elseif ($field->type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $field->inputName() }}" rows="3" maxlength="2000" @required($field->required) class="{{ $class }}">{{ $current }}</textarea>
        @else
            <input id="{{ $id }}" name="{{ $field->inputName() }}" value="{{ $current }}" @required($field->required) class="{{ $class }}"
                type="{{ match ($field->type) { 'date' => 'date', 'time' => 'time', 'number', 'money', 'duration' => 'number', default => 'text' } }}"
                @if ($field->type === 'money') step="0.01" min="0" @elseif ($field->type === 'number') step="any" @elseif ($field->type === 'duration') min="5" max="720" @endif>
        @endif
    @endif
    @error($field->path())<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
