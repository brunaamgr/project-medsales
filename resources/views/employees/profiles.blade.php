<x-layouts.app title="Perfis de acesso">
    <x-flash />
    <p class="mb-4 max-w-3xl text-sm text-stone-600">Marque o que cada perfil pode fazer. O Super Adm pode sempre tudo que foi contratado. O Adm precisa ter tudo o que o Comum tem.</p>

    <form method="POST" action="{{ route('profiles.update') }}" class="max-w-4xl space-y-6">
        @csrf @method('PUT')
        <x-card title="Alcance">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($editable as $profile)
                    <label class="text-sm font-medium">{{ $profile->label() }} enxerga
                        <select name="{{ $profile->value }}[scope]" class="mt-1 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 font-normal">
                            @foreach ($scopes as $value => $label)
                                <option value="{{ $value }}" @selected(old($profile->value.'.scope', $current[$profile->value]['scope']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
        </x-card>

        @foreach ($modules as $module => $permissions)
            <section>
                <h2 class="mb-2 text-base font-semibold">{{ $module === 'Núcleo' ? 'Administração' : $module }}</h2>
                <x-table>
                    <thead><tr><th>O que pode fazer</th>@foreach ($editable as $profile)<th class="w-24">{{ $profile->label() }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($permissions as $slug => $description)
                            <tr>
                                <td>{{ $description }}</td>
                                @foreach ($editable as $profile)
                                    <td>
                                        @if ($profile->value === 'comum' && ! in_array($slug, $allowed['comum'], true))
                                            <span class="text-stone-500" title="Não disponível para este perfil">—</span>
                                        @else
                                            <input type="checkbox" name="{{ $profile->value }}[permissions][]" value="{{ $slug }}" aria-label="{{ $profile->label() }}: {{ $description }}" @checked(in_array($slug, session()->hasOldInput() ? old($profile->value.'.permissions', []) : $current[$profile->value]['permissions'], true))>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </section>
        @endforeach
        <x-button>Salvar perfis</x-button>
    </form>
</x-layouts.app>
