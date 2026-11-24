<?php

namespace App\Modules\Operacao\Forms;

/** Um campo do formulário já com a configuração do cliente aplicada. */
final readonly class FieldDefinition
{
    /**
     * @param  array<array-key, string>  $options  valor => rótulo
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $custom,
        public bool $visible,
        public bool $required,
        public int $position,
        public array $options = [],
        public ?string $catalog = null,
    ) {}

    public function inputName(): string
    {
        return $this->custom ? "custom[{$this->key}]" : $this->key;
    }

    public function path(): string
    {
        return $this->custom ? "custom.{$this->key}" : $this->key;
    }
}
