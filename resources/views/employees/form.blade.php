<x-layouts.app :title="$member ? 'Editar funcionário' : 'Novo funcionário'">
    <x-flash />
    <form method="POST" action="{{ $member ? route('employees.update', $member) : route('employees.store') }}" class="max-w-4xl space-y-6" autocomplete="off">
        @csrf
        @if ($member) @method('PUT') @endif

        <x-card title="Dados da pessoa">
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="name" label="Nome *" :value="$employee?->name ?? $member?->user?->name" required />
                <x-field name="cpf" label="CPF *" :value="$employee?->cpf" inputmode="numeric" maxlength="14" required />
                <x-field name="rg" label="RG" :value="$employee?->rg" maxlength="30" />
                <x-field name="phone" label="Telefone" type="tel" :value="$employee?->phone" />
                <x-field name="email" label="E-mail" type="email" :value="$employee?->email" />
                <x-field name="job_title" label="Cargo" :value="$employee?->job_title" />
                <x-field name="department" label="Setor" :value="$employee?->department" />
            </div>
        </x-card>

        <x-card title="Acesso ao sistema">
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="login" :label="$managed ? 'Login *' : 'Login'" :value="$member?->login" autocapitalize="none" maxlength="40" :required="$managed" />
                @if ($self)
                    <p class="text-sm"><span class="block font-medium">Perfil de acesso</span>{{ $currentLabel }}. Você não pode mudar o próprio perfil.</p>
                @else
                    <div>
                        <label for="profile" class="mb-1 block text-sm font-medium">Perfil de acesso *</label>
                        <select id="profile" name="profile" @required(! $member || $current) class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm">
                            <option value="">{{ $member && ! $current ? 'Manter: '.$currentLabel : 'Selecione' }}</option>
                            @foreach ($assignable as $profile)
                                <option value="{{ $profile->value }}" @selected(old('profile', $current?->value) === $profile->value)>{{ $profile->label() }}</option>
                            @endforeach
                        </select>
                        @error('profile')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endif
                @unless ($member)
                    <x-field name="password" label="Senha * (mínimo de 12 caracteres)" type="password" autocomplete="new-password" required />
                    <x-field name="password_confirmation" label="Confirme a senha *" type="password" autocomplete="new-password" required />
                @endunless
                @if ($units->count() > 1)
                    <fieldset class="text-sm md:col-span-2">
                        <legend class="mb-1 font-medium">Unidades *</legend>
                        @foreach ($units as $unit)
                            <label class="mr-4 inline-flex items-center gap-2"><input type="checkbox" name="units[]" value="{{ $unit->public_id }}" @checked(in_array($unit->public_id, old('units', $units->whereIn('id', $memberUnits)->pluck('public_id')->all()), true))> {{ $unit->name }}</label>
                        @endforeach
                        @error('units')<p class="mt-1 text-red-700">{{ $message }}</p>@enderror
                    </fieldset>
                @endif
            </div>
        </x-card>
        <x-button>{{ $member ? 'Salvar' : 'Cadastrar' }}</x-button>
    </form>

    @if ($member && ! $self)
        <div class="mt-8 max-w-4xl space-y-6">
            @can('core.usuario.reset_password')
                <x-card title="Trocar senha">
                    @if ($managed)
                        <form method="POST" action="{{ route('employees.password', $member) }}" class="grid gap-4 md:grid-cols-2">
                            @csrf @method('PUT')
                            <x-field name="password" label="Nova senha (mínimo de 12 caracteres)" type="password" autocomplete="new-password" required />
                            <x-field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
                            <div><x-button>Trocar senha</x-button></div>
                        </form>
                    @else
                        <p class="text-sm text-stone-600">Esta conta usa e-mail próprio; a pessoa troca a senha por "Esqueci minha senha".</p>
                    @endif
                </x-card>
            @endcan
            @can('core.usuario.suspend')
                <x-card :title="$member->status === 'active' ? 'Suspender acesso' : 'Reativar acesso'">
                    <form method="POST" action="{{ route('employees.status', $member) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="status" value="{{ $member->status === 'active' ? 'suspended' : 'active' }}">
                        <x-button>{{ $member->status === 'active' ? 'Suspender' : 'Reativar' }}</x-button>
                    </form>
                </x-card>
            @endcan
            @can('core.usuario.delete')
                <x-card title="Excluir funcionário">
                    <details>
                        <summary class="cursor-pointer text-sm font-medium text-red-700">Quero excluir</summary>
                        <p class="my-3 text-sm text-stone-600">A pessoa perde o acesso e CPF, RG, telefone e e-mail são apagados. Não dá para desfazer. O histórico do que ela fez permanece.</p>
                        <form method="POST" action="{{ route('employees.destroy', $member) }}">
                            @csrf @method('DELETE')
                            <x-button>Confirmar exclusão</x-button>
                        </form>
                    </details>
                </x-card>
            @endcan
        </div>
    @endif
</x-layouts.app>
