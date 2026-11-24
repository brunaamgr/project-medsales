<?php

namespace Tests\Fixtures;

use App\Core\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordTenantJob implements ShouldQueue
{
    use Queueable;

    public static ?int $seen = null;

    public static int $runs = 0;

    public function handle(TenantContext $context): void
    {
        self::$runs++;
        self::$seen = $context->id();
    }
}
