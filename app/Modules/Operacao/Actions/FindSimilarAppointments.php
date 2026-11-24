<?php

namespace App\Modules\Operacao\Actions;

use App\Modules\Operacao\Models\Appointment;
use Illuminate\Support\Carbon;

/** Avisos que não bloqueiam: consulta parecida do paciente e choque de horário do profissional. */
final class FindSimilarAppointments
{
    private const DAYS = 7;

    private const DEFAULT_MINUTES = 30;

    /** @return list<string> */
    public function handle(?int $patientId, string $date, ?string $time, ?int $duration, ?int $professionalId, ?int $ignoreId): array
    {
        $warnings = [];
        $day = Carbon::parse($date);
        $base = Appointment::query()->whereNotIn('status', ['cancelled', 'no_show'])->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        if ($patientId !== null) {
            $near = (clone $base)->where('patient_id', $patientId)
                ->whereDate('date', '>=', $day->copy()->subDays(self::DAYS))
                ->whereDate('date', '<=', $day->copy()->addDays(self::DAYS))->orderBy('date')->first();

            if ($near !== null) {
                $warnings[] = "Este paciente já tem a consulta {$near->number} em {$near->date->format('d/m/Y')}.";
            }
        }

        if ($professionalId !== null && $time !== null) {
            [$start, $end] = $this->range($time, $duration);

            foreach ((clone $base)->where('professional_id', $professionalId)->whereDate('date', $day)->whereNotNull('start_time')->get() as $other) {
                [$from, $to] = $this->range((string) $other->time(), $other->duration_minutes);

                if ($start < $to && $end > $from) {
                    $warnings[] = "O profissional já tem a consulta {$other->number} às {$other->time()}.";
                    break;
                }
            }
        }

        return $warnings;
    }

    /** @return array{int, int} minutos desde a meia-noite */
    private function range(string $time, ?int $duration): array
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $time));
        $start = $hour * 60 + $minute;

        return [$start, $start + ($duration ?? self::DEFAULT_MINUTES)];
    }
}
