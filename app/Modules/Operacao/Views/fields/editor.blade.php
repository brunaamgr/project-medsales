@if ($canUpdate)
<x-card title="Começar por um modelo" class="mb-6">
    <form method="POST" action="{{ $routes['preset'] }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <label class="text-sm">Modelo
            <select name="preset" class="block rounded-md border border-stone-300 bg-white px-3 py-2">
                @foreach ($presets as $key => $preset)<option value="{{ $key }}">{{ $preset['name'] }}</option>@endforeach
            </select>
        </label>
        <x-button>Aplicar modelo</x-button>
    </form>
    <p class="mt-2 text-sm text-stone-600">Troca quais campos padrão aparecem. Os campos criados e os dados já gravados ficam; campos excluídos não voltam por aqui.</p>
</x-card>

@endif
<form method="POST" action="{{ $routes['update'] }}" class="mb-6">
    @csrf @method('PUT')
    <x-table>
        <thead><tr><th>Rótulo</th><th>Tipo</th><th>Aparece</th><th>Obrigatório</th><th>Ordem</th>@if ($canDelete)<th>Excluir</th>@endif</tr></thead>
        <tbody>
            @foreach ($fields as $key => $field)
                <tr>
                    <td>
                        <input name="fields[{{ $key }}][label]" value="{{ old("fields.$key.label", $field->label) }}" maxlength="80" required aria-label="Rótulo de {{ $field->label }}" class="w-full min-w-40 rounded-md border border-stone-300 px-2 py-1">
                        @if ($field->custom && $field->type === 'select')
                            <textarea name="fields[{{ $key }}][options]" rows="3" aria-label="Opções de {{ $field->label }}" class="mt-2 w-full rounded-md border border-stone-300 px-2 py-1">{{ implode("\n", $field->options) }}</textarea>
                        @endif
                    </td>
                    <td>{{ $field->custom ? $types[$field->type].' (criado)' : 'Padrão' }}</td>
                    <td><input type="hidden" name="fields[{{ $key }}][visible]" value="0"><input type="checkbox" name="fields[{{ $key }}][visible]" value="1" @checked($field->visible) aria-label="{{ $field->label }} aparece"></td>
                    <td><input type="hidden" name="fields[{{ $key }}][required]" value="0"><input type="checkbox" name="fields[{{ $key }}][required]" value="1" @checked($field->required) aria-label="{{ $field->label }} obrigatório"></td>
                    <td><input type="number" name="fields[{{ $key }}][position]" value="{{ $field->position }}" min="0" max="9999" required aria-label="Ordem de {{ $field->label }}" class="w-20 rounded-md border border-stone-300 px-2 py-1"></td>
                    @if ($canDelete)
                        <td>
                            <details>
                                <summary class="cursor-pointer text-red-700" aria-label="Excluir {{ $field->label }}">Excluir</summary>
                                <p class="my-2 w-44 text-xs text-stone-600">Sai do formulário. O que já foi gravado fica e o campo pode ser restaurado.</p>
                                <button type="submit" form="excluir-{{ $key }}" class="rounded-md bg-red-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-800">Confirmar</button>
                            </details>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </x-table>
    <p class="mt-2 text-sm text-stone-600">Paciente, data e status aparecem sempre. Ocultar um campo não apaga o que já foi gravado.</p>
    @if ($canUpdate)
        <x-button class="mt-4">Salvar campos</x-button>
    @endif
</form>

@if ($canDelete)
    {{-- Formulários fora da tabela: HTML não aceita um dentro do outro. --}}
    @foreach ($fields as $key => $field)
        <form id="excluir-{{ $key }}" method="POST" action="{{ $routes['field']('destroy', $key) }}" hidden>@csrf @method('DELETE')</form>
    @endforeach

    @if ($removed->isNotEmpty())
        <x-card title="Campos excluídos" class="mb-6">
            <ul class="divide-y divide-stone-100 text-sm">
                @foreach ($removed as $row)
                    <li class="flex flex-wrap items-center gap-4 py-3">
                        <span class="grow">{{ $row->label }} <span class="text-stone-600">· {{ $row->custom ? $types[$row->type].' (criado)' : 'Padrão' }} · excluído em {{ \App\Shared\Support\LocalTime::format($row->removed_at) }}</span></span>
                        <form method="POST" action="{{ $routes['field']('restore', $row->key) }}">@csrf @method('PUT')<x-button>Restaurar</x-button></form>
                        @if ($row->custom)
                            <details>
                                <summary class="cursor-pointer text-red-700" aria-label="Apagar de vez {{ $row->label }}">Apagar de vez</summary>
                                <form method="POST" action="{{ $routes['field']('purge', $row->key) }}" class="mt-2">
                                    @csrf @method('DELETE')
                                    <p class="mb-2 text-xs text-stone-600">Não dá para desfazer. Só funciona se o campo nunca foi preenchido.</p>
                                    <button class="rounded-md bg-red-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-800">Confirmar</button>
                                </form>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endif

@if ($canCreate)
<x-card title="Criar campo">
    <form method="POST" action="{{ $routes['store'] }}" class="grid gap-4 md:grid-cols-2">
        @csrf
        <x-field name="label" label="Rótulo" required maxlength="80" />
        <label class="text-sm font-medium">Tipo
            <select name="type" class="mt-1 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 font-normal">
                @foreach ($types as $key => $name)<option value="{{ $key }}" @selected(old('type') === $key)>{{ $name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm font-medium md:col-span-2">Opções, uma por linha (só para lista)
            <textarea name="options" rows="3" class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 font-normal">{{ old('options') }}</textarea>
        </label>
        <div><x-button>Criar campo</x-button></div>
    </form>
    <p class="mt-2 text-sm text-stone-600">O tipo não pode ser alterado depois.</p>
</x-card>
@endif
