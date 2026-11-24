<?php

namespace App\Core\Features\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo global (sem tenant), espelhado dos manifestos.
 *
 * @property int $id
 * @property int $module_id
 * @property string $slug
 */
#[Fillable(['module_id', 'slug', 'name'])]
class Feature extends Model {}
