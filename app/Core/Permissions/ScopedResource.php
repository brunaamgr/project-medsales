<?php

namespace App\Core\Permissions;

/** Model de domínio que aceita escopos unit/own. Coluna nula = escopo não se aplica. */
interface ScopedResource
{
    public function unitColumn(): ?string;

    public function ownerColumn(): ?string;
}
