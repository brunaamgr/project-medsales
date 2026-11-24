<?php

namespace App\Modules\Operacao\Forms;

/**
 * Campos padrão da agenda. A lista é a união do que Pinhabel e Mychelle usam;
 * cada cliente escolhe o que aparece. Paciente, data e status são sempre fixos.
 */
final class AppointmentForm
{
    public const FORM = 'agendamento';

    /** Tipos que o cliente pode criar. */
    public const CUSTOM_TYPES = [
        'text' => 'Texto curto', 'textarea' => 'Texto longo', 'number' => 'Número', 'money' => 'Valor em R$',
        'date' => 'Data', 'time' => 'Hora', 'select' => 'Lista de opções', 'checkbox' => 'Sim/Não',
    ];

    /** Modelos prontos: chave => [visíveis, obrigatórios]. É ponto de partida, editável depois. */
    public const PRESETS = [
        'essencial' => ['name' => 'Essencial', 'visible' => ['start_time', 'professional_id', 'notes'], 'required' => []],
        'consulta_reserva' => [
            'name' => 'Consulta com reserva',
            'visible' => ['booked_on', 'attendance_type_id', 'service_type_id', 'professional_id', 'location_id', 'price', 'deposit_status', 'deposit_amount'],
            'required' => ['booked_on', 'attendance_type_id', 'professional_id', 'price'],
        ],
        'horario_duracao' => [
            'name' => 'Agenda com horário e duração',
            'visible' => ['booked_on', 'start_time', 'duration_minutes', 'service_type_id', 'attendance_type_id', 'professional_id', 'procedure_id', 'price', 'deposit_status', 'notes'],
            'required' => ['start_time', 'service_type_id', 'deposit_status'],
        ],
    ];

    public const DEPOSIT = ['none' => 'Sem reserva', 'open' => 'Reserva em aberto', 'paid' => 'Reserva paga', 'full' => 'Pagamento total'];

    /** @return array<string, array{label: string, type: string, catalog?: string, options?: array<string, string>}> */
    public static function standard(): array
    {
        return [
            'booked_on' => ['label' => 'Data do agendamento', 'type' => 'date'],
            'start_time' => ['label' => 'Horário', 'type' => 'time'],
            'duration_minutes' => ['label' => 'Duração (minutos)', 'type' => 'duration'],
            'service_type_id' => ['label' => 'Tipo de consulta', 'type' => 'catalog', 'catalog' => 'service_type'],
            'attendance_type_id' => ['label' => 'Tipo de atendimento', 'type' => 'catalog', 'catalog' => 'attendance_type'],
            'professional_id' => ['label' => 'Profissional', 'type' => 'catalog', 'catalog' => 'professional'],
            'location_id' => ['label' => 'Local', 'type' => 'catalog', 'catalog' => 'location'],
            'insurance_id' => ['label' => 'Convênio', 'type' => 'catalog', 'catalog' => 'insurance'],
            'procedure_id' => ['label' => 'Procedimento', 'type' => 'catalog', 'catalog' => 'procedure'],
            'price' => ['label' => 'Valor (R$)', 'type' => 'money'],
            'deposit_status' => ['label' => 'Reserva', 'type' => 'select', 'options' => self::DEPOSIT],
            'deposit_amount' => ['label' => 'Valor da reserva (R$)', 'type' => 'money'],
            'notes' => ['label' => 'Observações', 'type' => 'textarea'],
        ];
    }
}
