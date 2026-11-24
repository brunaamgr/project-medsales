<?php

use App\Core\Features\Models\Feature;
use App\Core\Features\Models\TenantFeature;
use App\Core\Modules\Models\TenantModule;
use App\Core\Permissions\Models\Permission;
use App\Core\Permissions\Models\ResourceAcl;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\Exceptions\CrossTenantAccess;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Widget;

beforeEach(function () {
    Widget::createTable();
    $this->syncAccess();
    $this->a = $this->tenant('alfa');
    $this->b = $this->tenant('beta');
});

it('sincronização é idempotente e toda permissão tem escopo válido', function () {
    $before = [Role::query()->count(), Permission::query()->count(), DB::table('role_permissions')->count()];
    $this->syncAccess();

    expect([Role::query()->count(), Permission::query()->count(), DB::table('role_permissions')->count()])->toBe($before)
        ->and(Role::query()->count())->toBe(11)
        ->and(DB::table('role_permissions')->whereNotIn('scope', ['tenant', 'unit', 'own', 'assigned'])->count())->toBe(0);
});

it('papel de sistema não duplica no banco', function () {
    DB::table('roles')->insert(['slug' => 'doctor', 'name' => 'x']);
})->throws(QueryException::class);

it('sincronização remove permissão retirada do papel', function () {
    $role = Role::system('reception');
    $role->permissions()->attach(Permission::query()->where('slug', 'core.audit.view')->sole()->id, ['scope' => 'tenant']);
    $this->syncAccess();

    expect($role->permissions()->where('slug', 'core.audit.view')->exists())->toBeFalse();
});

it('nega por padrão', function () {
    $this->enable($this->a, 'operacao', ['agenda']);
    $user = $this->member($this->a);
    $this->context()->set($this->a);

    expect($this->access()->allows($user, 'operacao.agenda.view'))->toBeFalse()
        ->and($user->can('inexistente.recurso.acao'))->toBeFalse();
});

it('permissão exige módulo e funcionalidade contratados', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->context()->set($this->a);
    expect($this->access()->allows($user, 'operacao.agenda.view'))->toBeFalse();

    $this->enable($this->a, 'operacao', ['agenda']);
    expect($this->access()->allows($user, 'operacao.agenda.view'))->toBeTrue()
        ->and($this->access()->allows($user, 'operacao.estoque.view'))->toBeFalse();
});

it('funcionalidade ligada com módulo desligado não libera', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->enable($this->a, 'operacao', ['agenda']);
    $this->context()->set($this->a);
    TenantModule::query()->sole()->update(['enabled' => false]);

    expect($this->access()->allows($user, 'operacao.agenda.view'))->toBeFalse();
});

it('papel em um tenant não vale em outro', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->member($this->b, null, $user);
    $this->enable($this->b, 'operacao', ['agenda']);
    $this->context()->set($this->b);

    expect($this->access()->allows($user, 'operacao.agenda.view'))->toBeFalse()
        ->and($this->access()->allows($user, 'core.usuario.view'))->toBeFalse();
});

it('usuário ou vínculo suspenso perde tudo', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->context()->set($this->a);
    expect($this->access()->allows($user, 'core.usuario.view'))->toBeTrue();

    Membership::query()->sole()->update(['status' => 'suspended']);
    expect($this->access()->allows($user, 'core.usuario.view'))->toBeFalse();

    Membership::query()->sole()->update(['status' => 'active']);
    $user->forceFill(['status' => 'suspended'])->save();
    expect($this->access()->allows($user, 'core.usuario.view'))->toBeFalse();
});

it('escopo own só alcança os próprios registros', function () {
    $this->enable($this->a, 'operacao', ['cirurgias']);
    $doctor = $this->member($this->a, 'doctor');
    $this->context()->set($this->a);
    $mine = Widget::query()->create(['label' => 'meu', 'owner_id' => $doctor->id]);
    $other = Widget::query()->create(['label' => 'outro', 'owner_id' => $doctor->id + 99]);
    $access = $this->access();

    expect($access->allows($doctor, 'operacao.cirurgia.view', $mine))->toBeTrue()
        ->and($access->allows($doctor, 'operacao.cirurgia.view', $other))->toBeFalse()
        ->and($access->restrict(Widget::query(), $doctor, 'operacao.cirurgia.view')->pluck('label')->all())->toBe(['meu']);
});

it('escopo unit só alcança unidades vinculadas', function () {
    $this->enable($this->a, 'operacao', ['agenda']);
    $user = $this->member($this->a, 'reception');
    $this->context()->set($this->a);
    [$u1, $u2] = [Unit::query()->create(['name' => 'U1']), Unit::query()->create(['name' => 'U2'])];
    Membership::query()->sole()->assignUnit($u1);
    $in = Widget::query()->create(['label' => 'dentro', 'unit_id' => $u1->id]);
    $out = Widget::query()->create(['label' => 'fora', 'unit_id' => $u2->id]);
    $none = Widget::query()->create(['label' => 'sem unidade']);
    $access = $this->access();

    expect($access->allows($user, 'operacao.agenda.view', $in))->toBeTrue()
        ->and($access->allows($user, 'operacao.agenda.view', $out))->toBeFalse()
        ->and($access->allows($user, 'operacao.agenda.view', $none))->toBeFalse()
        ->and($access->restrict(Widget::query(), $user, 'operacao.agenda.view')->pluck('label')->all())->toBe(['dentro']);
});

it('ACL pontual concede acesso até expirar', function () {
    $this->enable($this->a, 'operacao', ['cirurgias']);
    $doctor = $this->member($this->a, 'doctor');
    $this->context()->set($this->a);
    $widget = Widget::query()->create(['label' => 'caso', 'owner_id' => $doctor->id + 99]);
    $acl = ResourceAcl::query()->create([
        'membership_id' => Membership::query()->sole()->id,
        'permission_id' => Permission::query()->where('slug', 'operacao.cirurgia.view')->sole()->id,
        'resource_type' => $widget->getMorphClass(), 'resource_id' => $widget->id,
        'expires_at' => now()->addHour(), 'created_by' => $doctor->id,
    ]);

    expect($this->access()->allows($doctor, 'operacao.cirurgia.view', $widget))->toBeTrue()
        ->and($this->access()->restrict(Widget::query(), $doctor, 'operacao.cirurgia.view')->count())->toBe(1);

    $acl->update(['expires_at' => now()->subMinute()]);
    expect($this->access()->allows($doctor, 'operacao.cirurgia.view', $widget))->toBeFalse()
        ->and($this->access()->restrict(Widget::query(), $doctor, 'operacao.cirurgia.view')->count())->toBe(0);
});

it('recurso de outro tenant é negado mesmo com escopo tenant', function () {
    $owner = $this->member($this->a, 'tenant_owner');
    $foreign = $this->context()->run($this->b, fn () => Widget::query()->create(['label' => 'B']));
    $this->context()->set($this->a);

    expect($this->access()->allows($owner, 'core.usuario.view', $foreign))->toBeFalse();
});

it('não atribui papel de plataforma nem papel de outro tenant', function () {
    $this->member($this->a);
    $this->context()->set($this->a);
    $membership = Membership::query()->sole();
    $custom = (new Role)->forceFill(['tenant_id' => $this->b->id, 'slug' => 'x', 'name' => 'X']);
    $custom->save();

    expect(fn () => $membership->assignRole(Role::system('platform_admin')))->toThrow(CrossTenantAccess::class)
        ->and(fn () => $membership->assignRole($custom))->toThrow(CrossTenantAccess::class);
});

it('separa financeiro de clínico e recepção de financeiro', function () {
    $this->enable($this->a, 'operacao', ['agenda', 'financeiro', 'cirurgias']);
    $finance = $this->member($this->a, 'finance');
    $reception = $this->member($this->a, 'reception');
    $this->context()->set($this->a);
    $access = $this->access();

    expect($access->allows($finance, 'operacao.financeiro.edit'))->toBeTrue()
        ->and($access->allows($finance, 'operacao.paciente.view'))->toBeFalse()
        ->and($access->allows($reception, 'operacao.financeiro.view'))->toBeFalse();
});

it('rotas respeitam módulo, funcionalidade e permissão', function () {
    Route::middleware(['web', 'auth', 'tenant.member', 'module:operacao', 'feature:operacao.estoque', 'permission:operacao.estoque.view'])
        ->get('/t/estoque', fn () => 'estoque');
    $owner = $this->member($this->a, 'tenant_owner');
    $this->login($this->a, $owner);

    $this->get($this->url($this->a, '/operacao'))->assertNotFound();
    $this->enable($this->a, 'operacao', ['pacientes']);
    $this->get($this->url($this->a, '/operacao'))->assertOk();
    $this->get($this->url($this->a, '/t/estoque'))->assertNotFound();

    $this->context()->run($this->a, fn () => TenantFeature::query()->create([
        'feature_id' => Feature::query()->where('slug', 'operacao.estoque')->sole()->id, 'enabled' => true,
    ]));
    $this->get($this->url($this->a, '/t/estoque'))->assertOk();

    $reception = $this->member($this->a, 'reception');
    $this->post($this->url($this->a, '/logout'));
    $this->login($this->a, $reception);
    $this->get($this->url($this->a, '/t/estoque'))->assertForbidden();
});
