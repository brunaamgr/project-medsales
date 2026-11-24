<?php

namespace App\Core\Employees;

/** Perfis de acesso de todo cliente. O que Adm e Comum podem fazer é configuração. */
enum Profile: string
{
    case SuperAdm = 'super_adm';
    case Adm = 'adm';
    case Comum = 'comum';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdm => 'Super Adm',
            self::Adm => 'Adm',
            self::Comum => 'Comum',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::SuperAdm => 90,
            self::Adm => 80,
            self::Comum => 20,
        };
    }

    /** @return list<self> os que o cliente configura */
    public static function editable(): array
    {
        return [self::Comum, self::Adm];
    }
}
