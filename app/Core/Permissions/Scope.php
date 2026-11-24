<?php

namespace App\Core\Permissions;

enum Scope: string
{
    case Tenant = 'tenant';
    case Unit = 'unit';
    case Own = 'own';
    case Assigned = 'assigned';
}
