<?php

namespace App\Core\Invitations;

use App\Core\Invitations\Models\Invitation;

/** O token em claro só existe aqui, até o envio. */
final readonly class IssuedInvitation
{
    public function __construct(public Invitation $invitation, public string $url) {}
}
