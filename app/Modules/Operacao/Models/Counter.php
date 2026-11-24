<?php

namespace App\Modules\Operacao\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** @property int $value */
#[Table('operacao_counters')]
#[Fillable(['name', 'value'])]
class Counter extends Model
{
    use BelongsToTenant;

    /** Chamar dentro de transação: a linha fica travada até o commit. */
    public static function next(string $name): int
    {
        self::query()->firstOrCreate(['name' => $name], ['value' => 0]);
        $counter = self::query()->where('name', $name)->lockForUpdate()->sole();
        $counter->update(['value' => $counter->value + 1]);

        return $counter->value;
    }
}
