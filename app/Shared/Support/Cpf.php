<?php

namespace App\Shared\Support;

final class Cpf
{
    public static function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    public static function isValid(string $value): bool
    {
        $cpf = self::digits($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * ($length + 1 - $i);
            }

            if ((int) $cpf[$length] !== ($sum * 10) % 11 % 10) {
                return false;
            }
        }

        return true;
    }

    public static function mask(?string $value): string
    {
        $cpf = self::digits((string) $value);

        return strlen($cpf) === 11 ? '***.'.substr($cpf, 3, 3).'.***-**' : '—';
    }

    /** Índice cego: permite checar duplicidade sem guardar o CPF em claro. */
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', self::digits($value), config()->string('app.key'));
    }
}
