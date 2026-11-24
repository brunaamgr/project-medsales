<?php

namespace App\Core\Permissions;

use Illuminate\Console\Command;

final class SyncAccessCommand extends Command
{
    protected $signature = 'access:sync';

    protected $description = 'Sincroniza módulos, funcionalidades, permissões e papéis de sistema';

    public function handle(AccessSynchronizer $synchronizer): int
    {
        $synchronizer->sync();
        $this->info('Catálogo de acesso sincronizado.');

        return self::SUCCESS;
    }
}
