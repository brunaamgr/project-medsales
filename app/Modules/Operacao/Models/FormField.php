<?php

namespace App\Modules\Operacao\Models;

use App\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $form
 * @property string $key
 * @property bool $custom
 * @property string $label
 * @property string $type
 * @property list<string>|null $options
 * @property bool $visible
 * @property bool $required
 * @property int $position
 * @property Carbon|null $removed_at excluído do formulário, mas recuperável
 */
#[Table('operacao_form_fields')]
#[Fillable(['form', 'key', 'custom', 'label', 'type', 'options', 'visible', 'required', 'position'])]
class FormField extends Model
{
    use BelongsToTenant;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['custom' => 'boolean', 'options' => 'array', 'visible' => 'boolean', 'required' => 'boolean', 'position' => 'integer', 'removed_at' => 'datetime'];
    }
}
