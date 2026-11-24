<?php

namespace App\Core\Tenancy\Exceptions;

use RuntimeException;

final class MissingTenantContext extends RuntimeException
{
    protected $message = 'Operação em dado de tenant sem contexto de tenant.';
}
