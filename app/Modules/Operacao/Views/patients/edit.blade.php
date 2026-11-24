<x-layouts.app title="Editar paciente">
    <x-flash />
    <form method="POST" action="{{ route('operacao.pacientes.update', $patient) }}" class="max-w-md space-y-4">
        @csrf @method('PUT')
        <x-field name="name" label="Nome completo" :value="$patient->name" required />
        <x-field name="phone" label="Telefone" type="tel" :value="$patient->phone" />
        <x-button>Salvar</x-button>
    </form>
</x-layouts.app>
