@props(['name', 'label', 'type' => 'text', 'value' => null])
<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-erro" @enderror {{ $attributes->class(['w-full rounded-md border bg-white px-3 py-2 text-sm', 'border-red-500' => $errors->has($name), 'border-stone-300' => ! $errors->has($name)]) }}>
    @error($name)
        <p id="{{ $name }}-erro" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
