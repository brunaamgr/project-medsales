<?php

namespace App\Core\Permissions\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo global, espelhado dos manifestos.
 *
 * @property int $id
 * @property string $slug
 * @property string $module
 * @property string|null $feature
 */
#[Fillable(['slug', 'module', 'feature', 'description'])]
class Permission extends Model {}
