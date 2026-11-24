<?php

namespace App\Core\Employees\Http;

use App\Core\Employees\AccessProfiles;
use App\Core\Employees\Profile;
use App\Core\Permissions\Scope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class ProfileController
{
    public const SCOPES = ['tenant' => 'Toda a clínica', 'unit' => 'Só a unidade da pessoa', 'own' => 'Só os próprios registros'];

    public function __construct(private AccessProfiles $profiles) {}

    public function edit(): View
    {
        return view('employees.profiles', [
            'modules' => $this->profiles->grantable(Profile::Adm),
            'allowed' => ['comum' => array_keys(array_merge(...array_values($this->profiles->grantable(Profile::Comum)) ?: [[]]))],
            'editable' => Profile::editable(),
            'current' => ['comum' => $this->profiles->current(Profile::Comum), 'adm' => $this->profiles->current(Profile::Adm)],
            'scopes' => self::SCOPES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        foreach (Profile::editable() as $profile) {
            $allowed = array_keys(array_merge(...array_values($this->profiles->grantable($profile)) ?: [[]]));
            $rules["{$profile->value}.scope"] = ['required', Rule::in([Scope::Tenant->value, Scope::Unit->value, Scope::Own->value])];
            $rules["{$profile->value}.permissions"] = ['nullable', 'array'];
            $rules["{$profile->value}.permissions.*"] = ['string', 'distinct', Rule::in($allowed)];
        }

        $data = $request->validate($rules);
        $this->profiles->save(array_map(fn (array $p): array => ['scope' => $p['scope'], 'permissions' => array_values($p['permissions'] ?? [])], $data));

        return back()->with('status', 'Perfis atualizados. A mudança já vale para todos.');
    }
}
