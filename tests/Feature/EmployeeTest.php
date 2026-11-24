<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Employees\AccessProfiles;
use App\Core\Employees\EmployeeService;
use App\Core\Employees\Models\Employee;
use App\Core\Employees\Profile;
use App\Core\Features\Models\Feature;
use App\Core\Features\Models\TenantFeature;
use App\Core\Permissions\AccessResolver;
use App\Core\Permissions\AccessSynchronizer;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use App\Modules\Operacao\Models\Appointment;
use App\Shared\Support\Cpf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// CPFs sintéticos, gerados só pelo dígito verificador.
const CPF_A = '111.444.777-35';
const CPF_B = '529.982.247-25';
const SENHA = 'senha-de-teste-123';

beforeEach(function () {
    $this->syncAccess();
    [$this->a, $this->b] = [$this->tenant('alfa'), $this->tenant('beta')];
    $this->enable($this->a, 'operacao', ['agenda', 'pacientes', 'cadastros']);
    $this->enable($this->b, 'operacao', ['agenda', 'pacientes', 'cadastros']);
    $this->owner = $this->member($this->a, 'tenant_owner');
    $this->in = fn ($tenant, Closure $fn) => $this->context()->run($tenant, $fn);
    $this->form = fn (array $changes = []) => ['name' => 'Pessoa Teste', 'cpf' => CPF_A, 'login' => 'pessoa', 'profile' => 'comum', 'password' => SENHA, 'password_confirmation' => SENHA, ...$changes];
    $this->add = fn (array $changes = []) => $this->post($this->url($this->a, '/equipe'), ($this->form)($changes));
    $this->find = fn (string $login, $tenant = null) => ($this->in)($tenant ?? $this->a, fn () => Membership::query()->where('login', $login)->with(['user', 'roles'])->first());
    $this->enter = fn (string $login, $tenant = null, string $password = SENHA) => $this->post($this->url($tenant ?? $this->a, '/login'), ['login' => $login, 'password' => $password]);
    $this->leave = fn ($tenant = null) => $this->post($this->url($tenant ?? $this->a, '/logout'));
    $this->to = fn (Membership $m, string $path = '') => $this->url($this->a, "/equipe/{$m->public_id}{$path}");
    // Faz a pessoa existir e entrar com o perfil pedido.
    $this->as = function (string $profile, string $login, string $cpf) {
        $this->login($this->a, $this->owner);
        ($this->add)(['login' => $login, 'cpf' => $cpf, 'profile' => $profile])->assertSessionHasNoErrors();
        ($this->leave)();
        ($this->enter)($login)->assertRedirect();

        return ($this->find)($login);
    };
    $this->perfis = fn (array $comum, array $adm, string $sc = 'unit', string $sa = 'tenant') => $this->put($this->url($this->a, '/perfis'), ['comum' => ['scope' => $sc, 'permissions' => $comum], 'adm' => ['scope' => $sa, 'permissions' => $adm]]);
});

it('cadastra funcionário que entra com login e senha e só acessa o que o perfil libera', function () {
    ($this->as)('comum', 'pessoa', CPF_A);

    $this->get($this->url($this->a, '/'))->assertOk()->assertSee('Operação')->assertDontSee('Equipe');
    $this->get($this->url($this->a, '/operacao/agenda'))->assertOk();
    $this->get($this->url($this->a, '/operacao/agenda/nova'))->assertOk();
    foreach (['/equipe', '/equipe/novo', '/perfis', '/auditoria', '/operacao/campos', '/operacao/cadastros'] as $path) {
        $this->get($this->url($this->a, $path))->assertForbidden();
    }
});

it('exige nome, CPF, login, senha e perfil', function () {
    $this->login($this->a, $this->owner);
    $this->post($this->url($this->a, '/equipe'), [])->assertSessionHasErrors(['name', 'cpf', 'login', 'password', 'profile'])
        ->assertSessionDoesntHaveErrors(['rg', 'phone', 'email', 'job_title', 'department']);
});

it('valida os dados', function (array $change, string $field) {
    $this->login($this->a, $this->owner);
    ($this->add)($change)->assertSessionHasErrors($field);
})->with([
    'CPF com dígito errado' => [['cpf' => '111.444.777-36'], 'cpf'],
    'CPF repetido' => [['cpf' => '111.111.111-11'], 'cpf'],
    'CPF curto' => [['cpf' => '123'], 'cpf'],
    'login curto' => [['login' => 'ab'], 'login'],
    'login com espaço' => [['login' => 'ana maria'], 'login'],
    'login que parece e-mail' => [['login' => 'ana@x.com'], 'login'],
    'senha curta' => [['password' => 'curta', 'password_confirmation' => 'curta'], 'password'],
    'senha sem confirmação' => [['password_confirmation' => 'outra-senha-1234'], 'password'],
    'perfil inexistente' => [['profile' => 'root'], 'profile'],
    'e-mail inválido' => [['email' => 'x'], 'email'],
]);

it('login e CPF são únicos no cliente e independentes entre clientes', function () {
    $this->login($this->a, $this->owner);
    ($this->add)(['login' => 'Maria'])->assertSessionHasNoErrors();
    ($this->add)(['login' => 'maria', 'cpf' => CPF_B])->assertSessionHasErrors('login');
    ($this->add)(['login' => 'outra', 'cpf' => '11144477735'])->assertSessionHasErrors('cpf');
    ($this->leave)();

    $this->login($this->b, $this->member($this->b, 'tenant_owner'));
    $this->post($this->url($this->b, '/equipe'), ($this->form)(['login' => 'maria', 'password' => 'outra-senha-1234', 'password_confirmation' => 'outra-senha-1234']))->assertSessionHasNoErrors();
    ($this->leave)($this->b);

    expect(($this->find)('maria')->user_id)->not->toBe(($this->find)('maria', $this->b)->user_id);
    // A senha de um cliente não abre o outro.
    ($this->enter)('maria', $this->b)->assertSessionHasErrors('login');
    ($this->enter)('maria', $this->a, 'outra-senha-1234')->assertSessionHasErrors('login');
    ($this->enter)('maria', $this->b, 'outra-senha-1234')->assertRedirect($this->url($this->b, '/'));
});

it('login de funcionário não entra na plataforma', function () {
    $this->login($this->a, $this->owner);
    ($this->add)();
    ($this->leave)();
    $this->post('http://admin.localhost/login', ['login' => 'pessoa', 'password' => SENHA])->assertSessionHasErrors('login');
});

it('não alcança funcionário de outro cliente', function () {
    $other = $this->member($this->b, 'tenant_owner');
    $foreign = ($this->in)($this->b, fn () => Membership::query()->where('user_id', $other->id)->sole());
    $this->login($this->a, $this->owner);

    $this->get(($this->to)($foreign))->assertNotFound();
    $this->put(($this->to)($foreign), ($this->form)())->assertNotFound();
    $this->put(($this->to)($foreign, '/status'), ['status' => 'suspended'])->assertNotFound();
    $this->put(($this->to)($foreign, '/senha'), ['password' => SENHA, 'password_confirmation' => SENHA])->assertNotFound();
    $this->delete(($this->to)($foreign))->assertNotFound();
    $this->get($this->url($this->a, '/equipe'))->assertOk()->assertDontSee($other->email);
});

it('CPF e RG ficam criptografados, mascarados e fora da auditoria', function () {
    $this->login($this->a, $this->owner);
    ($this->add)(['rg' => 'RG-998877', 'phone' => '11900000000'])->assertSessionHasNoErrors();

    $row = json_encode(DB::table('employees')->first());
    expect($row)->not->toContain('11144477735')->not->toContain('998877');
    $this->get($this->url($this->a, '/equipe'))->assertSee('***.444.***-**')->assertDontSee('111.444.777')->assertDontSee('11144477735');
    $member = ($this->find)('pessoa');
    $this->get(($this->to)($member))->assertOk()->assertSee('11144477735');

    $logs = ($this->in)($this->a, fn () => AuditLog::query()->get());
    expect($logs->toJson())->not->toContain('11144477735')->not->toContain('998877')->not->toContain('Pessoa Teste')
        ->and($logs->pluck('action')->all())->toContain('funcionario.created', 'funcionario.viewed');
});

it('Adm só gerencia quem está abaixo e não cria Adm nem Super Adm', function () {
    $adm = ($this->as)('adm', 'gerente', CPF_A);
    ($this->leave)();
    $peer = ($this->as)('adm', 'gerente2', CPF_B);
    $ownerMember = ($this->in)($this->a, fn () => Membership::query()->where('user_id', $this->owner->id)->sole());

    foreach ([$ownerMember, $adm] as $target) {
        $this->get(($this->to)($target))->assertForbidden();
        $this->put(($this->to)($target), ($this->form)())->assertForbidden();
        $this->put(($this->to)($target, '/status'), ['status' => 'suspended'])->assertForbidden();
        $this->put(($this->to)($target, '/senha'), ['password' => SENHA, 'password_confirmation' => SENHA])->assertForbidden();
    }
    foreach (['adm', 'super_adm'] as $profile) {
        ($this->add)(['login' => 'novo', 'cpf' => '390.533.447-05', 'profile' => $profile])->assertSessionHasErrors('profile');
    }
    ($this->add)(['login' => 'novo', 'cpf' => '390.533.447-05'])->assertSessionHasNoErrors();
    $novo = ($this->find)('novo');
    $this->put(($this->to)($novo), ($this->form)(['login' => 'novo', 'cpf' => '390.533.447-05', 'profile' => 'adm']))->assertSessionHasErrors('profile');
    $this->get($this->url($this->a, '/perfis'))->assertForbidden();
    $this->delete(($this->to)($novo))->assertForbidden();
    expect($peer->login)->toBe('gerente2');
});

it('ninguém muda o próprio perfil, nem se suspende ou exclui', function () {
    $this->login($this->a, $this->owner);
    $self = ($this->in)($this->a, fn () => Membership::query()->where('user_id', $this->owner->id)->sole());

    $this->put(($this->to)($self), ($this->form)(['profile' => 'comum']))->assertForbidden();
    $this->put(($this->to)($self, '/status'), ['status' => 'suspended'])->assertForbidden();
    $this->delete(($this->to)($self))->assertForbidden();
    $this->put(($this->to)($self), ($this->form)(['profile' => '', 'name' => 'Dona Ajustada', 'login' => 'dona']))->assertSessionHasNoErrors();
    expect(($this->in)($this->a, fn () => Employee::query()->sole()->name))->toBe('Dona Ajustada');
});

it('guarda do último Super Adm barra no serviço', function (string $action) {
    $second = ($this->as)('super_adm', 'segundo', CPF_A);
    $first = ($this->in)($this->a, fn () => Membership::query()->where('user_id', $this->owner->id)->sole());
    $adm = User::query()->findOrFail($second->user_id);

    ($this->in)($this->a, function () use ($first, $adm, $action) {
        // O primeiro vira o único ativo: o segundo é suspenso depois de já ter sido lido como ator.
        $access = app(AccessResolver::class);
        $access->flush();
        $access->membership($adm);
        Membership::query()->where('user_id', $adm->id)->update(['status' => 'suspended']);
        $service = app(EmployeeService::class);
        $target = $first->fresh(['roles', 'user']);
        $call = match ($action) {
            'suspender' => fn () => $service->setStatus($target, 'suspended', $adm),
            'excluir' => fn () => $service->remove($target, $adm),
            'rebaixar' => fn () => $service->update($target, ['name' => 'X', 'cpf' => CPF_B, 'login' => 'x'], Profile::Comum, Unit::query()->get(), $adm),
        };
        expect($call)->toThrow(ValidationException::class);
        expect($target->fresh()->status)->toBe('active');
    });
})->with(['suspender', 'excluir', 'rebaixar']);

it('suspender, trocar perfil, senha ou excluir encerra o acesso na hora', function (string $action) {
    $member = ($this->as)('comum', 'pessoa', CPF_A);
    $this->get($this->url($this->a, '/operacao/agenda'))->assertOk();
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $member->user_id, 'payload' => '', 'last_activity' => 1]);
    ($this->leave)();
    DB::table('sessions')->updateOrInsert(['id' => 's1'], ['user_id' => $member->user_id, 'payload' => '', 'last_activity' => 1]);

    $this->login($this->a, $this->owner);
    match ($action) {
        'suspender' => $this->put(($this->to)($member, '/status'), ['status' => 'suspended'])->assertSessionHasNoErrors(),
        'perfil' => $this->put(($this->to)($member), ($this->form)(['profile' => 'adm']))->assertSessionHasNoErrors(),
        'senha' => $this->put(($this->to)($member, '/senha'), ['password' => 'nova-senha-segura-1', 'password_confirmation' => 'nova-senha-segura-1'])->assertSessionHasNoErrors(),
        'excluir' => $this->delete(($this->to)($member))->assertSessionHasNoErrors(),
    };
    expect(DB::table('sessions')->where('user_id', $member->user_id)->count())->toBe(0);
    ($this->leave)();

    $response = ($this->enter)('pessoa');
    $action === 'perfil' ? $response->assertRedirect($this->url($this->a, '/')) : $response->assertSessionHasErrors('login');
})->with(['suspender', 'perfil', 'senha', 'excluir']);

it('excluir apaga documentos, mantém o nome e libera login e CPF', function () {
    $this->login($this->a, $this->owner);
    ($this->add)(['rg' => 'RG-1', 'phone' => '119', 'email' => 'p@x.example']);
    $member = ($this->find)('pessoa');
    $this->delete(($this->to)($member))->assertRedirect();

    $employee = ($this->in)($this->a, fn () => Employee::query()->sole());
    expect([$employee->cpf, $employee->cpf_hash, $employee->rg, $employee->phone, $employee->email])->toBe([null, null, null, null, null])
        ->and($employee->name)->toBe('Pessoa Teste');
    $this->get($this->url($this->a, '/equipe'))->assertDontSee('Pessoa Teste');
    $this->get(($this->to)($member))->assertNotFound();
    $this->put(($this->to)($member, '/status'), ['status' => 'active'])->assertNotFound();
    ($this->add)()->assertSessionHasNoErrors();
});

it('não troca senha nem nome global de conta com e-mail próprio', function () {
    $shared = $this->member($this->a, 'reception');
    $this->member($this->b, 'tenant_owner', $shared);
    $member = ($this->in)($this->a, fn () => Membership::query()->where('user_id', $shared->id)->sole());
    $before = [$shared->password, $shared->name];
    $this->login($this->a, $this->owner);

    $this->put(($this->to)($member, '/senha'), ['password' => 'nova-senha-segura-1', 'password_confirmation' => 'nova-senha-segura-1'])->assertSessionHasErrors('password');
    $this->put(($this->to)($member), ($this->form)(['profile' => 'comum', 'name' => 'Outro Nome']))->assertSessionHasNoErrors();
    expect([$shared->refresh()->password, $shared->name])->toBe($before);
});

it('o cliente decide o que Comum e Adm podem, valendo no clique seguinte', function () {
    $this->login($this->a, $this->owner);
    $this->get($this->url($this->a, '/perfis'))->assertOk()->assertSee('Cancelar consultas')->assertDontSee('Gerenciar perfis');
    ($this->add)();
    $all = ['operacao.agenda.view', 'operacao.agenda.create', 'operacao.agenda.update', 'operacao.agenda.cancel'];
    ($this->perfis)(['operacao.agenda.view'], $all)->assertSessionHasNoErrors();
    ($this->leave)();

    ($this->enter)('pessoa');
    $this->get($this->url($this->a, '/operacao/agenda'))->assertOk();
    $this->get($this->url($this->a, '/operacao/agenda/nova'))->assertForbidden();
    $this->post($this->url($this->a, '/operacao/agenda'), ['date' => '2027-01-01', 'new_patient_name' => 'X'])->assertForbidden();
    $this->get($this->url($this->a, '/operacao/pacientes'))->assertForbidden();
    ($this->leave)();

    $this->login($this->a, $this->owner);
    ($this->perfis)($all, $all)->assertSessionHasNoErrors();
    ($this->leave)();
    ($this->enter)('pessoa');
    $this->get($this->url($this->a, '/operacao/agenda/nova'))->assertOk();
});

it('perfis de um cliente não mudam os de outro', function () {
    $this->login($this->a, $this->owner);
    ($this->perfis)([], [])->assertSessionHasNoErrors();
    $b = ($this->in)($this->b, fn () => app(AccessProfiles::class)->current(Profile::Comum));
    $a = ($this->in)($this->a, fn () => app(AccessProfiles::class)->current(Profile::Comum));

    expect($b['permissions'])->toContain('operacao.agenda.view')->and($a['permissions'])->not->toContain('operacao.agenda.view');
});

it('não concede permissão reservada, não contratada ou inexistente', function (string $permission) {
    $this->login($this->a, $this->owner);
    ($this->perfis)([], [$permission])->assertSessionHasErrors('adm.permissions.0');
})->with(['core.role.manage', 'core.billing.manage', 'core.tenant.manage', 'core.module.manage', 'precificacao.tabela.view', 'operacao.estoque.view', 'x.y.z']);

it('Comum nunca recebe administração e Adm precisa cobrir o Comum', function () {
    $this->login($this->a, $this->owner);
    ($this->perfis)(['core.usuario.view'], ['core.usuario.view'])->assertSessionHasErrors('comum.permissions.0');
    ($this->perfis)(['operacao.agenda.view'], [])->assertSessionHasErrors('comum');
    ($this->perfis)(['operacao.agenda.view'], ['operacao.agenda.view'], 'tenant', 'unit')->assertSessionHasErrors('comum');
});

it('salvar perfis preserva o que não está na tela', function () {
    $this->login($this->a, $this->owner);
    ($this->in)($this->a, fn () => app(AccessProfiles::class)->role(Profile::Adm));
    ($this->perfis)([], ['operacao.agenda.view'])->assertSessionHasNoErrors();
    $adm = ($this->in)($this->a, fn () => app(AccessProfiles::class)->current(Profile::Adm)['permissions']);

    expect($adm)->toContain('operacao.agenda.view', 'precificacao.tabela.view')->not->toContain('operacao.agenda.create', 'core.usuario.create');
});

it('pessoa com só cancelar e ver lê a consulta, cancela e não altera', function () {
    $this->login($this->a, $this->owner);
    ($this->perfis)(['operacao.agenda.view', 'operacao.agenda.cancel'], ['operacao.agenda.view', 'operacao.agenda.cancel'], 'tenant')->assertSessionHasNoErrors();
    $this->post($this->url($this->a, '/operacao/agenda'), ['date' => '2027-01-01', 'new_patient_name' => 'Paciente Sintético'])->assertSessionHasNoErrors();
    $url = $this->url($this->a, '/operacao/agenda/'.($this->in)($this->a, fn () => Appointment::query()->sole()->public_id));
    ($this->add)();
    ($this->leave)();
    ($this->enter)('pessoa');

    $this->get($url)->assertOk()->assertSee('Somente leitura')->assertSee('Cancelar consulta');
    $this->put($url, ['date' => '2027-02-02'])->assertForbidden();
    $this->put("{$url}/cancelar", ['cancel_reason' => 'pedido'])->assertSessionHasNoErrors();
    $this->put("{$url}/cancelar", ['cancel_reason' => 'pedido'])->assertStatus(409);
});

it('com várias unidades exige escolha e respeita a unidade da pessoa', function () {
    [$u1, $u2] = ($this->in)($this->a, fn () => [Unit::query()->create(['name' => 'U1']), Unit::query()->create(['name' => 'U2'])]);
    $foreign = ($this->in)($this->b, fn () => Unit::query()->create(['name' => 'UB']));
    $this->login($this->a, $this->owner);
    ($this->add)()->assertSessionHasErrors('units');
    ($this->add)(['units' => [$foreign->public_id]])->assertSessionHasErrors('units.0');
    ($this->add)(['units' => [$u2->public_id]])->assertSessionHasNoErrors();

    expect(($this->in)($this->a, fn () => ($this->find)('pessoa')->units()->pluck('name')->all()))->toBe(['U2'])->and($u1->exists)->toBeTrue();
});

it('sem unidade no cliente, cria a principal e vincula', function () {
    $this->login($this->a, $this->owner);
    ($this->add)()->assertSessionHasNoErrors();
    expect(($this->in)($this->a, fn () => ($this->find)('pessoa')->units()->pluck('name')->all()))->toBe(['Principal']);
});

it('a pessoa troca a própria senha', function () {
    ($this->as)('comum', 'pessoa', CPF_A);
    $url = $this->url($this->a, '/minha-senha');
    $this->get($url)->assertOk();
    $new = ['password' => 'nova-senha-segura-1', 'password_confirmation' => 'nova-senha-segura-1'];
    $this->put($url, ['current_password' => 'errada', ...$new])->assertSessionHasErrors('current_password');
    $this->put($url, ['current_password' => SENHA, ...$new])->assertRedirect($this->url($this->a, '/login'));
    ($this->enter)('pessoa')->assertSessionHasErrors('login');
    ($this->enter)('pessoa', null, 'nova-senha-segura-1')->assertRedirect($this->url($this->a, '/'));
});

it('quem já entrava por e-mail continua entrando', function () {
    ($this->enter)($this->owner->email)->assertRedirect($this->url($this->a, '/'));
});

it('limite de tentativas vale por login e por cliente', function () {
    foreach (range(1, 5) as $ignored) {
        ($this->enter)('pessoa', null, 'errada');
    }
    ($this->enter)('pessoa')->assertTooManyRequests();
    ($this->enter)('pessoa', $this->b)->assertSessionHasErrors('login');
});

it('funcionalidade essencial nasce ligada só em quem tem o módulo; permissão extinta some', function () {
    $c = $this->tenant('gama');
    foreach ([$this->a, $this->b] as $tenant) {
        ($this->in)($tenant, fn () => TenantFeature::query()->get()->filter(fn ($f) => $f->feature_id === Feature::query()->where('slug', 'operacao.pacientes')->sole()->id)->each->delete());
    }
    Feature::query()->where('slug', 'operacao.pacientes')->delete();
    Permission::query()->create(['slug' => 'operacao.agenda.manage', 'module' => 'operacao', 'feature' => null, 'description' => 'antiga']);
    app(AccessSynchronizer::class)->sync();

    $count = fn ($tenant) => ($this->in)($tenant, fn () => TenantFeature::query()->where('enabled', true)->where('feature_id', Feature::query()->where('slug', 'operacao.pacientes')->sole()->id)->count());
    expect([$count($this->a), $count($this->b), $count($c)])->toBe([1, 1, 0])
        ->and(Permission::query()->where('slug', 'operacao.agenda.manage')->exists())->toBeFalse();
});

it('Super Adm tem tudo que existe, menos o que é da plataforma', function () {
    $slugs = Role::system('tenant_owner')->permissions()->pluck('slug');
    expect($slugs->count())->toBe(Permission::query()->count() - 2)->and($slugs)->not->toContain('core.tenant.manage', 'core.module.manage');
});

it('papéis antigos mantêm o que tinham na agenda', function (string $role) {
    $slugs = Role::system($role)->permissions()->pluck('slug')->all();
    expect($slugs)->toContain('operacao.agenda.view', 'operacao.agenda.create', 'operacao.agenda.update', 'operacao.agenda.cancel');
})->with(['tenant_admin', 'manager', 'doctor', 'reception']);

it('valida CPF', function (string $cpf, bool $valid) {
    expect(Cpf::isValid($cpf))->toBe($valid);
})->with([[CPF_A, true], ['52998224725', true], ['52998224724', false], ['00000000000', false], ['', false], ['abc', false]]);

it('só o Super Adm configura perfis, mesmo entre os papéis antigos', function () {
    $this->login($this->a, $this->member($this->a, 'tenant_admin'));
    $this->get($this->url($this->a, '/perfis'))->assertForbidden();
    ($this->perfis)([], [])->assertForbidden();
});

it('quem acumula papéis conta pelo maior', function () {
    $member = ($this->as)('comum', 'pessoa', CPF_A);
    ($this->in)($this->a, function () use ($member) {
        $member->assignRole(Role::system('tenant_owner'));
        expect(app(AccessProfiles::class)->of($member->fresh('roles')))->toBe(Profile::SuperAdm);
    });
});
