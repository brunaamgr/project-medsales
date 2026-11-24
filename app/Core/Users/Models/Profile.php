<?php

namespace App\Core\Users\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Dados pessoais da identidade global (1:1 com User). */
#[Fillable(['phone', 'avatar_path', 'timezone'])]
class Profile extends Model {}
