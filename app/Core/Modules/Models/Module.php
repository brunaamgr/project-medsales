<?php

namespace App\Core\Modules\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo global (sem tenant), espelhado dos manifestos.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 */
#[Fillable(['slug', 'name', 'version'])]
class Module extends Model {}
