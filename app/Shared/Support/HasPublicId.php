<?php

namespace App\Shared\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** ULID público para URLs e APIs; o id interno nunca é exposto. */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            $model->setAttribute('public_id', $model->getAttribute('public_id') ?? (string) Str::ulid());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
