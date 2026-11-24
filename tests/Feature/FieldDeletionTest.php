<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Employees\AccessProfiles;
use App\Core\Employees\Profile;
use App\Core\Permissions\Models\Permission;
use App\Core\Platform\Models\PlatformAdmin;
use App\Core\Roles\Models\Role;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use App\Modules\Operacao\Forms\FormSchema;
use App\Modules\Operacao\Models\Appointment;
use App\Modules\Operacao\Models\FormField;

beforeEach(function () {
    $this->syncAccess();
    [$this->a, $this->b] = [$this->tenant('alfa'), $this->tenant('beta')];
    $this->enable($this->a, 'operacao', ['agenda', 'pacientes', 'cadastros']);
    $this->enable($this->b, 'operacao', ['agenda', 'pacientes', 'cadastros']);
    $this->owner = $this->member($this->a, 'tenant_owner');
    $this->in = fn ($tenant, Closure $fn) => $this->context()->run($tenant, $fn);
    $this->custom = fn ($tenant = null, string $label = 'Extra', string $type = 'text') => ($this->in)($tenant ?? $this->a, fn () => app(FormSchema::class)->addCustom($label, $type)->key);
    $this->keys = fn ($tenant = null) => ($this->in)($tenant ?? $this->a, fn () => array_keys(app(FormSchema::class)->fields()));
    $this->path = fn (string $key, string $suffix = '') => $this->url($this->a, "/operacao/campos/{$key}{$suffix}");
    $this->book = fn (array $data = []) => $this->post($this->url($this->a, '/operacao/agenda'), ['date' => '2027-01-10', 'new_patient_name' => 'Paciente Sintético', ...$data]);
    $this->appointment = fn () => ($this->in)($this->a, fn () => Appointment::query()->sole());
    $this->profileUser = function (Profile $profile) {
        $user = User::factory()->create();
        ($this->in)($this->a, function () use ($user, $profile) {
            $membership = Membership::query()->create(['user_id' => $user->id, 'status' => 'active']);
            $membership->assignRole(app(AccessProfiles::class)->role($profile));
        });

        return $user;
    };
    $this->platform = function () {
        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id, 'status' => 'active']);
        $this->post('http://admin.localhost/login', ['login' => $admin->email, 'password' => 'senha-de-teste-123']);

        return "http://admin.localhost/clientes/{$this->a->public_id}/operacao/campos";
    };
});

it('Super Adm exclui campo criado, que some do formulário e pode ser restaurado com os valores', function () {
    $key = ($this->custom)();
    $this->login($this->a, $this->owner);
    ($this->book)(['custom' => [$key => 'valor antigo']])->assertSessionHasNoErrors();

    $this->delete(($this->path)($key))->assertSessionHasNoErrors()->assertRedirect();
    expect(($this->keys)())->not->toContain($key);
    $this->get($this->url($this->a, '/operacao/agenda/nova'))->assertOk()->assertDontSee('Extra');
    $this->get($this->url($this->a, '/operacao/campos'))->assertOk()->assertSee('Campos excluídos')->assertSee('Extra');

    // Editar a consulta com o campo excluído não perde o valor.
    $appointment = ($this->appointment)();
    $this->put($this->url($this->a, "/operacao/agenda/{$appointment->public_id}"), ['patient' => ($this->in)($this->a, fn () => $appointment->patient->public_id), 'date' => '2027-02-01', 'custom' => [$key => 'intruso']])->assertSessionHasNoErrors();

    $this->put(($this->path)($key, '/restaurar'))->assertSessionHasNoErrors();
    expect(($this->keys)())->toContain($key)->and(($this->appointment)()->custom_values[$key])->toBe('valor antigo');
    $this->get($this->url($this->a, "/operacao/agenda/{$appointment->public_id}"))->assertSee('valor antigo');
});

it('campo criado e obrigatório deixa de ser exigido ao ser excluído', function () {
    $key = ($this->custom)();
    ($this->in)($this->a, fn () => FormField::query()->where('key', $key)->sole()->update(['required' => true]));
    $this->login($this->a, $this->owner);
    ($this->book)()->assertSessionHasErrors("custom.{$key}");
    $this->delete(($this->path)($key));
    ($this->book)()->assertSessionHasNoErrors();
});

it('exclui e restaura campo padrão com a configuração que tinha', function () {
    $this->login($this->a, $this->owner);
    ($this->in)($this->a, fn () => app(FormSchema::class)->applyPreset('consulta_reserva'));
    ($this->in)($this->a, fn () => FormField::query()->where('key', 'price')->sole()->update(['label' => 'Preço']));
    $this->delete(($this->path)('price'))->assertSessionHasNoErrors();
    expect(($this->keys)())->not->toContain('price');

    $this->put(($this->path)('price', '/restaurar'))->assertSessionHasNoErrors();
    $field = ($this->in)($this->a, fn () => app(FormSchema::class)->fields()['price']);
    expect([$field->label, $field->visible, $field->required])->toBe(['Preço', true, true]);
});

it('campo padrão nunca configurado também pode ser excluído', function () {
    $this->login($this->a, $this->owner);
    $this->delete(($this->path)('start_time'))->assertSessionHasNoErrors();
    expect(($this->keys)())->not->toContain('start_time');
});

it('modelo pronto e salvar campos não trazem de volta um campo excluído', function () {
    $this->login($this->a, $this->owner);
    $this->delete(($this->path)('start_time'));
    $this->post($this->url($this->a, '/operacao/campos/modelo'), ['preset' => 'horario_duracao'])->assertSessionHasNoErrors();
    $this->put($this->url($this->a, '/operacao/campos'), ['fields' => ['start_time' => ['label' => 'Horário', 'position' => 1, 'visible' => 1]]])->assertSessionHasNoErrors();
    expect(($this->keys)())->not->toContain('start_time');
});

it('Adm, Comum e papéis antigos não excluem, restauram nem apagam', function (Closure $make) {
    $key = ($this->custom)();
    $gone = ($this->custom)(null, 'Já excluído');
    ($this->in)($this->a, fn () => app(FormSchema::class)->remove($gone));
    $this->login($this->a, $make->call($this));

    $this->delete(($this->path)($key))->assertForbidden();
    $this->delete(($this->path)('start_time'))->assertForbidden();
    $this->put(($this->path)($gone, '/restaurar'))->assertForbidden();
    $this->delete(($this->path)($gone, '/definitivo'))->assertForbidden();
    expect(($this->keys)())->toContain($key, 'start_time')->not->toContain($gone);
})->with([
    'Adm' => [fn () => ($this->profileUser)(Profile::Adm)],
    'Comum' => [fn () => ($this->profileUser)(Profile::Comum)],
    'Administração (antigo)' => [fn () => $this->member($this->a, 'tenant_admin')],
    'Recepção (antigo)' => [fn () => $this->member($this->a, 'reception')],
    'Médico (antigo)' => [fn () => $this->member($this->a, 'doctor')],
]);

it('Adm vê a tela de campos sem as opções de exclusão', function () {
    ($this->in)($this->a, fn () => app(FormSchema::class)->remove('start_time'));
    $this->login($this->a, ($this->profileUser)(Profile::Adm));
    $this->get($this->url($this->a, '/operacao/campos'))->assertOk()->assertSee('Salvar campos')->assertDontSee('Excluir')->assertDontSee('Campos excluídos');
});

it('a permissão de excluir não pode ser dada a Adm nem Comum', function () {
    $this->login($this->a, $this->owner);
    $this->get($this->url($this->a, '/perfis'))->assertOk()->assertDontSee('Excluir campos da agenda');
    $this->put($this->url($this->a, '/perfis'), ['comum' => ['scope' => 'unit', 'permissions' => []], 'adm' => ['scope' => 'tenant', 'permissions' => ['operacao.campo.delete']]])->assertSessionHasErrors('adm.permissions.0');
});

it('mesmo gravada à força, a permissão sai do perfil ao salvar', function () {
    $role = ($this->in)($this->a, fn () => app(AccessProfiles::class)->role(Profile::Adm));
    expect($role->permissions()->where('slug', 'operacao.campo.delete')->exists())->toBeFalse();

    $role->permissions()->attach(Permission::query()->where('slug', 'operacao.campo.delete')->sole()->id, ['scope' => 'tenant']);
    $this->login($this->a, $this->owner);
    $this->put($this->url($this->a, '/perfis'), ['comum' => ['scope' => 'unit', 'permissions' => []], 'adm' => ['scope' => 'tenant', 'permissions' => []]])->assertSessionHasNoErrors();
    expect($role->permissions()->where('slug', 'operacao.campo.delete')->exists())->toBeFalse();
});

it('só o Super Adm tem a permissão entre os papéis de sistema', function () {
    expect(Role::query()->whereHas('permissions', fn ($q) => $q->where('slug', 'operacao.campo.delete'))->pluck('slug')->all())->toBe(['tenant_owner']);
});

it('apaga de vez só campo criado, excluído e nunca preenchido', function () {
    $used = ($this->custom)(null, 'Usado');
    $unused = ($this->custom)(null, 'Nunca usado');
    $this->login($this->a, $this->owner);
    ($this->book)(['custom' => [$used => 'x']]);

    // Ativo não pode ser apagado direto.
    $this->delete(($this->path)($unused, '/definitivo'))->assertNotFound();
    foreach ([$used, $unused, 'price'] as $key) {
        $this->delete(($this->path)($key))->assertSessionHasNoErrors();
    }
    $this->delete(($this->path)($used, '/definitivo'))->assertSessionHasErrors('campo');
    $this->delete(($this->path)($unused, '/definitivo'))->assertSessionHasNoErrors();
    $this->delete(($this->path)('price', '/definitivo'))->assertNotFound();

    expect(($this->in)($this->a, fn () => FormField::query()->pluck('key')->all()))->toContain($used, 'price')->not->toContain($unused);
});

it('consulta cancelada com o campo preenchido também impede apagar de vez', function () {
    $key = ($this->custom)(null, 'Aceite', 'checkbox');
    $this->login($this->a, $this->owner);
    ($this->book)(['custom' => [$key => '0']]);
    $this->put($this->url($this->a, '/operacao/agenda/'.($this->appointment)()->public_id.'/cancelar'), ['cancel_reason' => 'x']);
    $this->delete(($this->path)($key));
    $this->delete(($this->path)($key, '/definitivo'))->assertSessionHasErrors('campo');
});

it('não alcança campo de outro cliente', function () {
    $foreign = ($this->custom)($this->b, 'Do Beta');
    $this->login($this->a, $this->owner);
    $this->delete(($this->path)($foreign))->assertNotFound();
    ($this->in)($this->b, fn () => app(FormSchema::class)->remove($foreign));
    $this->put(($this->path)($foreign, '/restaurar'))->assertNotFound();
    $this->delete(($this->path)($foreign, '/definitivo'))->assertNotFound();

    $this->delete(($this->path)('price'));
    expect(($this->keys)($this->b))->toContain('price');
});

it('campo inexistente, repetido ou fixo devolve 404', function () {
    $this->login($this->a, $this->owner);
    foreach (['nao_existe', 'patient', 'date', 'status'] as $key) {
        $this->delete(($this->path)($key))->assertNotFound();
    }
    $this->delete(($this->path)('price'))->assertRedirect();
    $this->delete(($this->path)('price'))->assertNotFound();
    $this->put(($this->path)('notes', '/restaurar'))->assertNotFound();
});

it('o administrador do painel exclui, restaura e apaga campos do cliente, com auditoria dos dois lados', function () {
    $key = ($this->custom)();
    $base = ($this->platform)();
    $this->get($base)->assertOk()->assertSee('Excluir Extra');
    $this->delete("{$base}/{$key}")->assertSessionHasNoErrors();
    expect(($this->keys)())->not->toContain($key);
    $this->get($base)->assertSee('Campos excluídos');
    $this->put("{$base}/{$key}/restaurar")->assertSessionHasNoErrors();
    expect(($this->keys)())->toContain($key);
    $this->delete("{$base}/{$key}");
    $this->delete("{$base}/{$key}/definitivo")->assertSessionHasNoErrors();

    $this->assertDatabaseHas('audit_logs', ['tenant_id' => null, 'action' => 'platform.tenant.operacao_fields']);
    expect(($this->in)($this->a, fn () => AuditLog::query()->pluck('action')->all()))->toContain('operacao.campos.removed', 'operacao.campos.restored', 'operacao.campos.purged')
        ->and(($this->in)($this->b, fn () => FormField::query()->count()))->toBe(0);
});

it('a plataforma não exclui campo de cliente sem o módulo', function () {
    $base = ($this->platform)();
    $c = $this->tenant('gama');
    $this->delete("http://admin.localhost/clientes/{$c->public_id}/operacao/campos/price")->assertNotFound();
    expect($base)->toContain($this->a->public_id);
});

it('campo excluído não conta no limite, e restaurar respeita o limite', function () {
    $gone = ($this->custom)();
    ($this->in)($this->a, fn () => app(FormSchema::class)->remove($gone));
    ($this->in)($this->a, function () {
        foreach (range(1, FormSchema::MAX_CUSTOM) as $i) {
            app(FormSchema::class)->addCustom("C{$i}", 'text');
        }
    });
    $this->login($this->a, $this->owner);
    $this->post($this->url($this->a, '/operacao/campos'), ['label' => 'mais um', 'type' => 'text'])->assertSessionHasErrors('label');
    $this->put(($this->path)($gone, '/restaurar'))->assertSessionHasErrors('campo');
});
