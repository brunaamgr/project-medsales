<x-layouts.app title="Operação">
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @can('operacao.agenda.view')
            <li><x-area-link :href="route('operacao.agenda.index')" title="Agenda" text="Consultas do dia e agendamentos." /></li>
        @endcan
        @can('operacao.paciente.view')
            <li><x-area-link :href="route('operacao.pacientes.index')" title="Pacientes" text="Busca e edição de cadastro." /></li>
        @endcan
        @can('operacao.cadastro.view')
            <li><x-area-link :href="route('operacao.cadastros.index')" title="Cadastros" text="Profissionais, tipos, locais e convênios." /></li>
        @endcan
        @can('operacao.campo.view')
            @if (app(\App\Core\Modules\Entitlements::class)->feature('operacao.agenda'))
                <li><x-area-link :href="route('operacao.campos.edit')" title="Personalizar campos" text="O que aparece no formulário da agenda." /></li>
            @endif
        @endcan
    </ul>
</x-layouts.app>
