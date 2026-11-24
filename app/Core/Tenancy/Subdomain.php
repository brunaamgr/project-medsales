<?php

namespace App\Core\Tenancy;

final class Subdomain
{
    /** 3–63 caracteres, a-z/0-9/hífen, sem hífen nas pontas, sem punycode e fora da lista reservada. */
    public static function isValid(string $value): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/D', $value) === 1
            && ! str_starts_with($value, 'xn--')
            && ! in_array($value, config()->array('tenancy.reserved_subdomains'), true);
    }
}
