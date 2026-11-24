<x-layouts.app :title="$appointment ? 'Consulta '.$appointment->number : 'Nova consulta'">
    <x-flash />
    @php($cancelled = $appointment?->status === 'cancelled')
    @php($locked = $cancelled || ! $canSave)

    @if ($cancelled)
        <x-alert tone="error" class="mb-4">Consulta cancelada. Motivo: {{ $appointment->cancel_reason }}</x-alert>
    @endif

    @if ($locked && ! $cancelled)
        <x-alert class="mb-4">Somente leitura: seu perfil não altera esta consulta.</x-alert>
    @endif

    <form method="POST" action="{{ $appointment ? route('operacao.agenda.update', $appointment) : route('operacao.agenda.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($appointment) @method('PUT') @endif

        @if (session('similar'))
            <x-alert tone="error">
                <ul class="list-disc pl-5">@foreach (session('similar') as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                <label class="mt-3 flex items-center gap-2 font-medium"><input type="checkbox" name="confirm_similar" value="1"> Conferi e quero salvar mesmo assim</label>
            </x-alert>
        @endif

        <fieldset @disabled($locked)>
        <x-card>
            <div class="grid gap-4 md:grid-cols-2">
                <div x-data="patientPicker" data-url="{{ route('operacao.pacientes.lookup') }}" data-id="{{ old('patient', $patient?->public_id) }}" data-name="{{ old('new_patient_name') ? '' : $patient?->name }}" class="relative">
                    <label for="patient-search" class="mb-1 block text-sm font-medium">Paciente</label>
                    <input type="hidden" name="patient" x-bind:value="id" value="{{ old('patient', $patient?->public_id) }}">
                    <input id="patient-search" type="text" autocomplete="off" value="{{ old('new_patient_name') ? '' : $patient?->name }}" x-on:input.debounce.300ms="search" placeholder="Digite ao menos 2 letras" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm">
                    <ul x-ref="results" class="mt-1 divide-y divide-stone-100 rounded-md border border-stone-200 bg-white text-sm empty:hidden"></ul>
                    @error('patient')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                @can('operacao.paciente.create')
                    @unless ($appointment)
                        <details @if (old('new_patient_name')) open @endif class="rounded-md border border-stone-200 p-3">
                            <summary class="cursor-pointer text-sm font-medium">Paciente novo</summary>
                            <div class="mt-3 space-y-3">
                                <x-field name="new_patient_name" label="Nome completo" autocomplete="off" />
                                <x-field name="new_patient_phone" label="Telefone" type="tel" autocomplete="off" />
                            </div>
                        </details>
                    @endunless
                @endcan
                <x-field name="date" label="Data da consulta" type="date" :value="$appointment?->date->format('Y-m-d') ?? $today" required />
                @if ($units->count() > 1)
                    <div>
                        <label for="unit" class="mb-1 block text-sm font-medium">Unidade</label>
                        <select id="unit" name="unit" required class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm">
                            <option value="">Selecione</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->public_id }}" @selected(old('unit', $appointment?->unit_id === $unit->id ? $unit->public_id : null) === $unit->public_id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($appointment && ! $locked)
                    <div>
                        <label for="status" class="mb-1 block text-sm font-medium">Status</label>
                        <select id="status" name="status" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm">
                            @foreach (\App\Modules\Operacao\Models\Appointment::STATUSES as $value => $label)
                                @continue($value === 'cancelled')
                                <option value="{{ $value }}" @selected(old('status', $appointment->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @foreach ($fields as $field)
                    @include('operacao::fields.input', ['field' => $field, 'value' => $appointment ? $schema->value($field, $appointment) : ($field->key === 'booked_on' ? $today : null), 'options' => $choices[$field->key] ?? []])
                @endforeach
            </div>
        </x-card>
        </fieldset>

        @unless ($locked)
            <x-button>{{ $appointment ? 'Salvar' : 'Agendar' }}</x-button>
        @endunless
    </form>

    @if ($canCancel && ! $cancelled)
        <x-card title="Cancelar consulta" class="mt-8 max-w-4xl">
            <form method="POST" action="{{ route('operacao.agenda.cancel', $appointment) }}" class="flex flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <div class="grow"><x-field name="cancel_reason" label="Motivo" required maxlength="255" /></div>
                <x-button>Cancelar consulta</x-button>
            </form>
        </x-card>
    @endif
</x-layouts.app>
