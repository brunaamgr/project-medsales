<?php

namespace App\Core\Employees\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Ficha da pessoa neste cliente. A conta de acesso está em Membership/User.
 *
 * @property int $membership_id
 * @property string $name
 * @property string|null $cpf
 * @property string|null $cpf_hash
 * @property string|null $rg
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $job_title
 * @property string|null $department
 */
#[Fillable(['membership_id', 'name', 'cpf', 'cpf_hash', 'rg', 'phone', 'email', 'job_title', 'department', 'created_by'])]
#[Hidden(['cpf', 'cpf_hash', 'rg'])]
class Employee extends Model
{
    use BelongsToTenant;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cpf' => 'encrypted', 'rg' => 'encrypted'];
    }
}
