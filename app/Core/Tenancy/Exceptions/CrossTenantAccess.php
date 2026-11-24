<?php

namespace App\Core\Tenancy\Exceptions;

use RuntimeException;

final class CrossTenantAccess extends RuntimeException
{
    protected $message = 'Tentativa de acesso cruzado entre tenants bloqueada.';
}
