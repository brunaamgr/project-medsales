<?php

namespace App\Modules\Operacao\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Users\Models\Membership;
use App\Modules\Operacao\Models\CatalogItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CatalogController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(string $type = 'professional'): View
    {
        abort_unless(isset(CatalogItem::TYPES[$type]), 404);

        return view('operacao::catalogs.index', [
            'type' => $type,
            'types' => CatalogItem::TYPES,
            'items' => CatalogItem::query()->where('type', $type)->orderByDesc('active')->orderBy('name')->get(),
            'members' => $this->members(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless(isset(CatalogItem::TYPES[$type]), 404);
        $item = CatalogItem::query()->create(['type' => $type, 'active' => true, ...$this->validated($request, $type)]);
        $this->audit->record('operacao.cadastro.created', $item, after: ['type' => $type]);

        return back()->with('status', 'Item adicionado.');
    }

    public function update(Request $request, CatalogItem $item): RedirectResponse
    {
        $item->update([...$this->validated($request, $item->type, $item), 'active' => $request->boolean('active')]);
        $this->audit->record('operacao.cadastro.updated', $item, after: ['type' => $item->type, 'active' => $item->active]);

        return back()->with('status', 'Item atualizado.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $type, ?CatalogItem $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($type, $item): void {
                if (CatalogItem::query()->where('type', $type)->where('name', $value)->when($item, fn ($q) => $q->whereKeyNot($item->id))->exists()) {
                    $fail('Já existe um item com este nome.');
                }
            }],
            'default_price' => ['nullable', 'numeric', 'between:0,9999999999.99'],
            'default_duration' => ['nullable', 'integer', 'between:5,720'],
            'user_id' => ['nullable', 'integer', Rule::in(array_keys($this->members()))],
        ]);

        return $type === 'professional' ? $data : [...$data, 'user_id' => null];
    }

    /** @return array<int, string> pessoas com vínculo ativo neste cliente */
    private function members(): array
    {
        return Membership::query()->where('status', 'active')->with('user')->get()
            ->mapWithKeys(fn (Membership $m): array => [$m->user_id => (string) $m->user?->name])->sort()->all();
    }
}
