<?php

namespace App\Modules\Operacao\Forms;

use App\Core\Audit\AuditLogger;
use App\Modules\Operacao\Models\Appointment;
use App\Modules\Operacao\Models\CatalogItem;
use App\Modules\Operacao\Models\FormField;
use App\Shared\Support\Transaction;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Formulário da agenda do tenant atual: padrão do código + configuração do cliente. */
final readonly class FormSchema
{
    public const MAX_CUSTOM = 30;

    /** Ativos e excluídos: evita crescer sem fim criando e excluindo. */
    public const MAX_TOTAL = 150;

    public function __construct(private AuditLogger $audit) {}

    /** @return array<string, FieldDefinition> em ordem de exibição */
    public function fields(): array
    {
        $rows = FormField::query()->where('form', AppointmentForm::FORM)->get()->keyBy('key');
        $preset = AppointmentForm::PRESETS['essencial'];
        $fields = [];
        $position = 0;

        foreach (AppointmentForm::standard() as $key => $base) {
            $row = $rows->get($key);

            if ($row?->removed_at !== null) {
                continue;
            }

            $fields[$key] = new FieldDefinition(
                $key, $row->label ?? $base['label'], $base['type'], false,
                $row->visible ?? in_array($key, $preset['visible'], true),
                $row->required ?? false,
                $row->position ?? ++$position * 10,
                $base['options'] ?? [], $base['catalog'] ?? null,
            );
        }

        foreach ($rows->where('custom', true)->whereNull('removed_at') as $row) {
            $options = $row->options ?? [];
            $fields[$row->key] = new FieldDefinition($row->key, $row->label, $row->type, true, $row->visible, $row->required, $row->position, array_combine($options, $options));
        }

        uasort($fields, fn (FieldDefinition $a, FieldDefinition $b): int => $a->position <=> $b->position);

        return $fields;
    }

    /** @return array<string, FieldDefinition> */
    public function visible(): array
    {
        return array_filter($this->fields(), fn (FieldDefinition $field): bool => $field->visible);
    }

    /**
     * Só campos visíveis entram; o resto do request é ignorado.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(?Appointment $current = null): array
    {
        $rules = ['custom' => ['array']];

        foreach ($this->visible() as $field) {
            $rules[$field->path()] = [
                $field->type === 'checkbox'
                    ? ($field->required ? 'accepted' : 'nullable')
                    : ($field->required ? 'required' : 'nullable'),
                ...$this->typeRules($field, $current),
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return array_column(array_map(fn (FieldDefinition $f): array => ['path' => $f->path(), 'label' => $f->label], $this->visible()), 'label', 'path');
    }

    /** @return array<string, array<array-key, string>> opções de cada lista usada no formulário */
    public function choices(?Appointment $current = null): array
    {
        $choices = [];
        $items = CatalogItem::query()->orderBy('name')->get();

        foreach ($this->visible() as $field) {
            if ($field->catalog !== null) {
                $value = $current?->getAttribute($field->key);
                $choices[$field->key] = $items->where('type', $field->catalog)
                    ->filter(fn (CatalogItem $item): bool => $item->active || $item->id === $value)
                    ->pluck('name', 'id')->all();
            } elseif ($field->type === 'select') {
                $value = $current === null ? null : $this->value($field, $current);
                $choices[$field->key] = $field->options + (is_string($value) && $value !== '' ? [$value => $field->options[$value] ?? $value] : []);
            }
        }

        return $choices;
    }

    public function value(FieldDefinition $field, Appointment $appointment): mixed
    {
        return $field->custom
            ? ($appointment->custom_values[$field->key] ?? null)
            : ($field->key === 'start_time' ? $appointment->time() : $appointment->getAttribute($field->key));
    }

    /** @param array<string, array<string, mixed>> $input */
    public function save(array $input): void
    {
        Transaction::run(function () use ($input): void {
            foreach ($this->fields() as $key => $field) {
                if (! isset($input[$key])) {
                    continue;
                }

                $visible = (bool) ($input[$key]['visible'] ?? false);
                $row = FormField::query()->firstOrNew(['form' => AppointmentForm::FORM, 'key' => $key]);
                $row->fill([
                    'custom' => $field->custom,
                    'type' => $field->type,
                    'label' => trim((string) $input[$key]['label']),
                    'visible' => $visible,
                    'required' => $visible && ($input[$key]['required'] ?? false),
                    'position' => (int) $input[$key]['position'],
                ]);

                if ($field->custom && $field->type === 'select') {
                    $row->options = self::parseOptions((string) ($input[$key]['options'] ?? ''));
                }

                $row->save();
            }

            $this->audit->record('operacao.campos.updated', after: $this->summary());
        });
    }

    /** O tipo não muda depois de criado: os valores já gravados dependem dele. */
    public function addCustom(string $label, string $type, string $options = ''): FormField
    {
        if (! isset(AppointmentForm::CUSTOM_TYPES[$type])) {
            throw new InvalidArgumentException("Tipo inválido: {$type}.");
        }

        $field = FormField::query()->create([
            'form' => AppointmentForm::FORM,
            'key' => 'c_'.strtolower((string) Str::ulid()),
            'custom' => true,
            'label' => trim($label),
            'type' => $type,
            'options' => $type === 'select' ? self::parseOptions($options) : null,
            'position' => 1 + max(0, ...array_map(fn (FieldDefinition $f): int => $f->position, array_values($this->fields()))),
        ]);

        $this->audit->record('operacao.campos.created', after: ['key' => $field->key, 'type' => $type]);

        return $field;
    }

    /** Campo padrão excluído não volta por aqui: restaurar é ato de quem pode excluir. */
    public function applyPreset(string $preset): void
    {
        $definition = AppointmentForm::PRESETS[$preset] ?? throw new InvalidArgumentException("Modelo inválido: {$preset}.");

        Transaction::run(function () use ($definition, $preset): void {
            foreach ($this->fields() as $key => $field) {
                if (! $field->custom) {
                    FormField::query()->updateOrCreate(['form' => AppointmentForm::FORM, 'key' => $key], [
                        'label' => $field->label,
                        'type' => $field->type,
                        'position' => $field->position,
                        'visible' => in_array($key, $definition['visible'], true),
                        'required' => in_array($key, $definition['required'], true),
                    ]);
                }
            }

            $this->audit->record('operacao.campos.preset', after: ['preset' => $preset]);
        });
    }

    /** @return Collection<int, FormField> */
    public function removed(): Collection
    {
        return FormField::query()->where('form', AppointmentForm::FORM)->whereNotNull('removed_at')->orderByDesc('removed_at')->get();
    }

    /** Sai do formulário; os valores já gravados nas consultas ficam. */
    public function remove(string $key): void
    {
        $field = $this->fields()[$key] ?? abort(404);

        Transaction::run(function () use ($key, $field): void {
            $row = FormField::query()->firstOrNew(['form' => AppointmentForm::FORM, 'key' => $key]);

            if (! $row->exists) {
                $row->fill([
                    'custom' => false, 'label' => $field->label, 'type' => $field->type,
                    'visible' => $field->visible, 'required' => $field->required, 'position' => $field->position,
                ]);
            }

            $row->forceFill(['removed_at' => now()])->save();
            $this->audit->record('operacao.campos.removed', after: ['campo' => $key]);
        });
    }

    public function restore(string $key): void
    {
        $row = $this->removedRow($key);

        if ($row->custom && $this->activeCustom() >= self::MAX_CUSTOM) {
            throw ValidationException::withMessages(['campo' => 'Limite de '.self::MAX_CUSTOM.' campos personalizados. Exclua outro antes de restaurar.']);
        }

        $row->forceFill(['removed_at' => null])->save();
        $this->audit->record('operacao.campos.restored', after: ['campo' => $key]);
    }

    /** Sem volta: só campo criado, já excluído e que nunca guardou valor. */
    public function purge(string $key): void
    {
        $row = $this->removedRow($key);
        abort_unless($row->custom, 404);

        if ($this->hasValues($key)) {
            throw ValidationException::withMessages(['campo' => 'Há consultas com este campo preenchido. Ele fica em "Campos excluídos" para não perder o dado.']);
        }

        $row->delete();
        $this->audit->record('operacao.campos.purged', after: ['campo' => $key]);
    }

    public function activeCustom(): int
    {
        return FormField::query()->where('custom', true)->whereNull('removed_at')->count();
    }

    private function removedRow(string $key): FormField
    {
        return FormField::query()->where('form', AppointmentForm::FORM)->where('key', $key)->whereNotNull('removed_at')->first() ?? abort(404);
    }

    /** Os valores são criptografados: não dá para filtrar no banco. Lê em lotes, só ao apagar de vez. */
    private function hasValues(string $key): bool
    {
        return Appointment::query()->whereNotNull('custom_values')->lazyById(200)
            ->contains(fn (Appointment $a): bool => ! in_array($a->custom_values[$key] ?? null, [null, ''], true));
    }

    /** @return list<string> */
    public static function parseOptions(string $text): array
    {
        $lines = array_map(trim(...), preg_split('/\R/', $text) ?: []);

        return array_slice(array_values(array_unique(array_filter($lines, fn (string $line): bool => $line !== ''))), 0, 100);
    }

    /** @return array<string, list<string>> */
    private function summary(): array
    {
        $visible = $this->visible();

        return [
            'visible' => array_keys($visible),
            'required' => array_keys(array_filter($visible, fn (FieldDefinition $f): bool => $f->required)),
        ];
    }

    /** @return list<mixed> */
    private function typeRules(FieldDefinition $field, ?Appointment $current): array
    {
        return match ($field->type) {
            'textarea' => ['string', 'max:2000'],
            'number' => ['numeric', 'between:-999999999,999999999'],
            'money' => ['numeric', 'between:0,9999999999.99', 'decimal:0,2'],
            'date' => ['date_format:Y-m-d'],
            'time' => ['date_format:H:i'],
            'duration' => ['integer', 'between:5,720'],
            'checkbox' => ['boolean'],
            'select' => [Rule::in(array_map(strval(...), array_keys($this->choices($current)[$field->key])))],
            'catalog' => ['integer', $this->catalogRule($field, $current)],
            default => ['string', 'max:255'],
        };
    }

    private function catalogRule(FieldDefinition $field, ?Appointment $current): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field, $current): void {
            $item = CatalogItem::query()->where('type', $field->catalog)->find((int) $value);

            if ($item === null || (! $item->active && $current?->getAttribute($field->key) !== $item->id)) {
                $fail('Escolha uma opção válida.');
            }
        };
    }
}
