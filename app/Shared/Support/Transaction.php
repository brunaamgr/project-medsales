<?php

namespace App\Shared\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/** Transação para os módulos, que não podem usar o facade DB (pula o escopo de tenant). */
final class Transaction
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
