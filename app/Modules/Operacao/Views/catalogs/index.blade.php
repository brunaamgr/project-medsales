<x-layouts.app title="Cadastros">
    <x-flash />
    <nav aria-label="Listas" class="mb-6 flex flex-wrap gap-2 text-sm">
        @foreach ($types as $key => $name)
            <a href="{{ route('operacao.cadastros.index', $key) }}" @if ($key === $type) aria-current="page" @endif @class(['rounded-md border px-3 py-1.5', 'border-brand-600 bg-brand-50 font-medium' => $key === $type, 'border-stone-300 bg-white' => $key !== $type])>{{ $name }}</a>
        @endforeach
    </nav>

    <div class="max-w-4xl space-y-3">
        @foreach ($items as $item)
            <form method="POST" action="{{ route('operacao.cadastros.update', $item) }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-stone-200 bg-white p-4 text-sm">
                @csrf @method('PUT')
                @include('operacao::catalogs.inputs', ['item' => $item])
                <label class="flex items-center gap-2 py-2"><input type="checkbox" name="active" value="1" @checked($item->active)> Ativo</label>
                @can('operacao.cadastro.update')
                    <x-button>Salvar</x-button>
                @endcan
            </form>
        @endforeach

        @can('operacao.cadastro.create')
        <x-card :title="'Adicionar em '.$types[$type]">
            <form method="POST" action="{{ route('operacao.cadastros.store', $type) }}" class="flex flex-wrap items-end gap-3 text-sm">
                @csrf
                @include('operacao::catalogs.inputs', ['item' => null])
                <x-button>Adicionar</x-button>
            </form>
            <p class="mt-2 text-sm text-stone-600">Itens não são apagados: desative para tirar das novas consultas.</p>
        </x-card>
        @endcan
    </div>
</x-layouts.app>
