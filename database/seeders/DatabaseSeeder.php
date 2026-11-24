<?php

namespace Database\Seeders;

use App\Core\Permissions\AccessSynchronizer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(AccessSynchronizer $access): void
    {
        $access->sync();
    }
}
