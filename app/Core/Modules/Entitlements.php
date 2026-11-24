<?php

namespace App\Core\Modules;

use App\Core\Features\Models\TenantFeature;
use App\Core\Modules\Models\TenantModule;
use App\Core\Tenancy\TenantContext;

/** O que o tenant atual contratou. Negado por padrão. */
final class Entitlements
{
    /** @var array<int, array{modules: list<string>, features: list<string>}> */
    private array $memo = [];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ModuleRegistry $registry,
    ) {}

    public function module(string $slug): bool
    {
        if ($slug === ModuleRegistry::CORE) {
            return true;
        }

        if (! $this->registry->has($slug) || ! in_array($slug, $this->load()['modules'], true)) {
            return false;
        }

        foreach ($this->registry->get($slug)->dependencies as $dependency) {
            if (! $this->module($dependency)) {
                return false;
            }
        }

        return true;
    }

    /** Recebe "modulo.funcionalidade"; exige os dois níveis. */
    public function feature(string $slug): bool
    {
        $module = strstr($slug, '.', true);

        return $module !== false
            && $this->module($module)
            && isset($this->registry->get($module)->features[$slug])
            && in_array($slug, $this->load()['features'], true);
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    /** @return array{modules: list<string>, features: list<string>} */
    private function load(): array
    {
        return $this->memo[$this->context->requireId()] ??= [
            'modules' => array_values(TenantModule::query()->where('enabled', true)->with('module')->get()
                ->map(fn (TenantModule $row): string => $row->module->slug)->all()),
            'features' => array_values(TenantFeature::query()->where('enabled', true)->with('feature')->get()
                ->map(fn (TenantFeature $row): string => $row->feature->slug)->all()),
        ];
    }
}
