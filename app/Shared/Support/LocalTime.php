<?php

namespace App\Shared\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Banco em UTC; tela em America/Sao_Paulo. */
final class LocalTime
{
    public static function format(DateTimeInterface $date, string $format = 'd/m/Y H:i'): string
    {
        return Carbon::instance($date)->setTimezone(config()->string('app.display_timezone'))->format($format);
    }
}
