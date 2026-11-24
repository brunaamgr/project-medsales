<?php

namespace App\Core\Platform;

use App\Core\Audit\AuditLogger;
use App\Core\Features\Models\Feature;
use App\Core\Features\Models\TenantFeature;
use App\Core\Modules\Entitlements;
use App\Core\Modules\Models\Module;
use App\Core\Modules\Models\TenantModule;
use App\Core\Modules\ModuleRegistry;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class SyncEntitlements
{
    public function __construct(
        private TenantContext $context,
        private ModuleRegistry $registry,
        private Entitlements $entitlements,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $modules
     * @param  list<string>  $features  no formato modulo.funcionalidade
     */
    public function handle(Tenant $tenant, array $modules, array $features): void
    {
        DB::transaction(function () use ($tenant, $modules, $features): void {
            [$before, $after] = $this->context->run($tenant, function () use ($modules, $features): array {
                $before = $this->snapshot();
                $moduleIds = Module::query()->pluck('id', 'slug');
                $featureIds = Feature::query()->pluck('id', 'slug');

                foreach ($this->registry->business() as $slug => $manifest) {
                    $enabled = in_array($slug, $modules, true);
                    TenantModule::query()->updateOrCreate(['module_id' => $moduleIds[$slug]], ['enabled' => $enabled]);

                    foreach (array_keys($manifest->features) as $feature) {
                        TenantFeature::query()->updateOrCreate(
                            ['feature_id' => $featureIds[$feature]],
                            ['enabled' => $enabled && in_array($feature, $features, true)],
                        );
                    }
                }

                $this->entitlements->flush();
                $after = $this->snapshot();

                // O cliente também enxerga a mudança na própria auditoria.
                $this->audit->record('tenant.entitlements.updated', before: $before, after: $after);

                return [$before, $after];
            });

            $this->audit->record('platform.tenant.entitlements', $tenant, $before, $after);
        });
    }

    /** @return array{modules: list<string>, features: list<string>} */
    public function snapshot(): array
    {
        return [
            'modules' => array_values(TenantModule::query()->where('enabled', true)->with('module')->get()->map(fn (TenantModule $m): string => $m->module->slug)->sort()->all()),
            'features' => array_values(TenantFeature::query()->where('enabled', true)->with('feature')->get()->map(fn (TenantFeature $f): string => $f->feature->slug)->sort()->all()),
        ];
    }
}
