<?php

namespace App\Modules\Operacao\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Modules\Entitlements;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Operacao\Forms\AppointmentForm;
use App\Modules\Operacao\Forms\FormSchema;
use App\Modules\Operacao\Models\FormField;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Mesma tela no cliente e no painel da plataforma; só muda o contexto. */
final class FieldController
{
    public function __construct(
        private readonly FormSchema $schema,
        private readonly TenantContext $context,
        private readonly Entitlements $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(?Tenant $tenant = null): View
    {
        return $this->within($tenant, fn (): View => view($tenant !== null ? 'operacao::fields.platform' : 'operacao::fields.tenant', [
            'tenant' => $tenant,
            'fields' => $this->schema->fields(),
            'removed' => $this->schema->removed(),
            'presets' => AppointmentForm::PRESETS,
            'types' => AppointmentForm::CUSTOM_TYPES,
            'routes' => $this->routes($tenant),
            'canUpdate' => $tenant !== null || request()->user()?->can('operacao.campo.update'),
            'canCreate' => $tenant !== null || request()->user()?->can('operacao.campo.create'),
            'canDelete' => $tenant !== null || request()->user()?->can('operacao.campo.delete'),
        ]));
    }

    public function update(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        return $this->within($tenant, function () use ($request): RedirectResponse {
            $data = $request->validate([
                'fields' => ['required', 'array', 'max:100'],
                'fields.*.label' => ['required', 'string', 'max:80'],
                'fields.*.position' => ['required', 'integer', 'between:0,9999'],
                'fields.*.visible' => ['nullable', 'boolean'],
                'fields.*.required' => ['nullable', 'boolean'],
                'fields.*.options' => ['nullable', 'string', 'max:5000'],
            ], [], ['fields.*.label' => 'rótulo', 'fields.*.position' => 'ordem']);
            $this->schema->save($data['fields']);

            return back()->with('status', 'Campos atualizados.');
        });
    }

    public function store(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        return $this->within($tenant, function () use ($request): RedirectResponse {
            $data = $request->validate([
                'label' => ['required', 'string', 'max:80'],
                'type' => ['required', Rule::in(array_keys(AppointmentForm::CUSTOM_TYPES))],
                'options' => ['nullable', 'string', 'max:5000', 'required_if:type,select'],
            ], ['options.required_if' => 'Informe ao menos uma opção, uma por linha.'], ['label' => 'rótulo']);

            if ($this->schema->activeCustom() >= FormSchema::MAX_CUSTOM || FormField::query()->count() >= FormSchema::MAX_TOTAL) {
                throw ValidationException::withMessages(['label' => 'Limite de '.FormSchema::MAX_CUSTOM.' campos personalizados.']);
            }

            $this->schema->addCustom($data['label'], $data['type'], $data['options'] ?? '');

            return back()->with('status', 'Campo criado.');
        });
    }

    public function preset(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        return $this->within($tenant, function () use ($request): RedirectResponse {
            $data = $request->validate(['preset' => ['required', Rule::in(array_keys(AppointmentForm::PRESETS))]]);
            $this->schema->applyPreset($data['preset']);

            return back()->with('status', 'Modelo aplicado.');
        });
    }

    public function destroy(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        return $this->within($tenant, function () use ($request): RedirectResponse {
            $this->schema->remove($this->key($request));

            return back()->with('status', 'Campo excluído. Você pode restaurá-lo em "Campos excluídos".');
        });
    }

    public function restore(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        return $this->within($tenant, function () use ($request): RedirectResponse {
            $this->schema->restore($this->key($request));

            return back()->with('status', 'Campo restaurado.');
        });
    }

    public function purge(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        return $this->within($tenant, function () use ($request): RedirectResponse {
            $this->schema->purge($this->key($request));

            return back()->with('status', 'Campo apagado de vez.');
        });
    }

    private function key(Request $request): string
    {
        return (string) $request->route('key');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function within(?Tenant $tenant, callable $callback): mixed
    {
        if (! $this->context->isPlatform()) {
            return $callback();
        }

        abort_unless($tenant?->exists === true, 404);

        $result = $this->context->run($tenant, function () use ($callback): mixed {
            abort_unless($this->entitlements->module('operacao'), 404);

            return $callback();
        });

        if (! request()->isMethodSafe()) {
            $this->audit->record('platform.tenant.operacao_fields', $tenant);
        }

        return $result;
    }

    /** @return array{update: string, store: string, preset: string, field: Closure(string, string): string} */
    private function routes(?Tenant $tenant): array
    {
        $platform = $tenant?->exists === true;
        $name = $platform ? 'platform.modules.operacao.' : 'operacao.campos.';
        $parameters = $platform ? [$tenant] : [];

        return [
            'update' => route($name.'update', $parameters),
            'store' => route($name.'store', $parameters),
            'preset' => route($name.'preset', $parameters),
            'field' => fn (string $action, string $key): string => route($name.$action, [...$parameters, $key]),
        ];
    }
}
