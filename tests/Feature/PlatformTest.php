<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Invitations\InvitationNotification;
use App\Core\Invitations\InvitationService;
use App\Core\Invitations\Models\Invitation;
use App\Core\Platform\Models\PlatformAdmin;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

const PLATFORM = 'http://admin.localhost';

beforeEach(function () {
    Notification::fake();
    $this->syncAccess();
    $this->admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $this->admin->id, 'status' => 'active']);
    $this->enter = fn (?User $user = null) => $this->post(PLATFORM.'/login', ['login' => ($user ?? $this->admin)->email, 'password' => 'senha-de-teste-123']);
    $this->form = ['name' => 'Clínica Nova', 'subdomain' => 'nova', 'owner_email' => 'Dona@Example.com', 'modules' => ['precificacao'], 'features' => ['precificacao.tabela', 'operacao.agenda']];
    $this->issue = fn (Tenant $tenant, string $email) => $this->context()->run($tenant, fn () => app(InvitationService::class)->issue($email, Role::system('tenant_owner'), $this->admin));
});

it('exige login e papel de plataforma', function () {
    $this->get(PLATFORM)->assertRedirect(PLATFORM.'/login');
    ($this->enter)()->assertRedirect(PLATFORM);
    $this->get(PLATFORM)->assertOk()->assertSee('Visão geral');
});

it('recusa quem não administra a plataforma', function (Closure $make) {
    ($this->enter)($make->call($this))->assertSessionHasErrors(['login' => 'Login ou senha inválidos.']);
    $this->assertGuest();
})->with([
    'responsável de cliente' => [fn () => $this->member($this->tenant('alfa'), 'tenant_owner')],
    'acesso revogado' => [function () {
        PlatformAdmin::query()->update(['status' => 'revoked']);

        return $this->admin;
    }],
    'usuário suspenso' => [function () {
        $this->admin->forceFill(['status' => 'suspended'])->save();

        return $this->admin;
    }],
]);

it('administrador da plataforma não entra em cliente sem vínculo', function () {
    $alfa = $this->tenant('alfa');
    $this->post($this->url($alfa, '/login'), ['login' => $this->admin->email, 'password' => 'senha-de-teste-123'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('sessão da plataforma não vale em cliente e vice-versa', function () {
    $alfa = $this->tenant('alfa');
    $this->member($alfa, 'tenant_owner', $this->admin);

    ($this->enter)();
    $this->get($this->url($alfa))->assertRedirect($this->url($alfa, '/login'));
    $this->assertGuest();

    $this->login($alfa, $this->admin);
    $this->get(PLATFORM)->assertRedirect(PLATFORM.'/login');
    $this->assertGuest();
});

it('rotas da plataforma não existem em cliente, nem as de cliente na plataforma', function () {
    $alfa = $this->tenant('alfa');
    $this->login($alfa, $this->member($alfa, 'tenant_owner'));
    $this->get($this->url($alfa, '/clientes'))->assertNotFound();
    $this->post($this->url($alfa, '/clientes'), $this->form)->assertNotFound();

    $this->post($this->url($alfa, '/logout'));
    ($this->enter)();
    $this->get(PLATFORM.'/precificacao')->assertNotFound();
    $this->get(PLATFORM.'/convite/abc')->assertNotFound();
});

it('revogar o acesso derruba a sessão', function () {
    ($this->enter)();
    PlatformAdmin::query()->update(['status' => 'revoked']);
    $this->get(PLATFORM)->assertRedirect(PLATFORM.'/login');
});

it('cadastra cliente, domínio, módulos e convite', function () {
    ($this->enter)();
    $this->post(PLATFORM.'/clientes', $this->form)->assertSessionHasNoErrors()->assertRedirect();
    $tenant = Tenant::query()->where('subdomain', 'nova')->sole();

    $this->assertDatabaseHas('tenant_domains', ['tenant_id' => $tenant->id, 'host' => 'nova.localhost']);
    Notification::assertSentOnDemand(InvitationNotification::class);
    $this->context()->set($tenant);
    expect(Invitation::query()->sole()->email)->toBe('dona@example.com');

    $owner = $this->member($tenant, 'tenant_owner');
    expect($this->access()->allows($owner, 'precificacao.tabela.view'))->toBeTrue()
        ->and($this->access()->allows($owner, 'precificacao.simulador.view'))->toBeFalse()
        ->and($this->access()->allows($owner, 'operacao.agenda.view'))->toBeFalse();
});

it('não grava o token do convite em claro', function () {
    $issued = ($this->issue)($this->tenant('alfa'), 'a@example.com');
    $token = basename($issued->url);

    $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);
    $this->assertDatabaseHas('invitations', ['token_hash' => hash('sha256', $token)]);
});

it('valida o subdomínio', function (string $value) {
    $this->tenant('alfa');
    ($this->enter)();
    $this->post(PLATFORM.'/clientes', [...$this->form, 'subdomain' => $value])->assertSessionHasErrors('subdomain');
    expect(Tenant::query()->count())->toBe(1);
})->with(['ab', 'admin', 'financeiro', 'com.ponto', 'com espaço', 'alfa', 'ALFA', '-abc']);

it('subdomínio de cliente excluído não é reaproveitado', function () {
    $this->tenant('alfa')->delete();
    ($this->enter)();
    $this->post(PLATFORM.'/clientes', [...$this->form, 'subdomain' => 'alfa'])->assertSessionHasErrors('subdomain');
});

it('informa disponibilidade', function (string $value, string $message) {
    $this->tenant('alfa');
    ($this->enter)();
    $this->getJson(PLATFORM.'/clientes/subdominio?valor='.$value)->assertExactJson(['message' => $message]);
})->with([['livre', 'Disponível.'], ['alfa', 'Já está em uso.'], ['www', 'Inválido ou reservado.']]);

it('recusa módulo ou funcionalidade inexistente', function () {
    ($this->enter)();
    $this->post(PLATFORM.'/clientes', [...$this->form, 'modules' => ['core']])->assertSessionHasErrors('modules.0');
    $this->post(PLATFORM.'/clientes', [...$this->form, 'features' => ['x.y']])->assertSessionHasErrors('features.0');
});

it('altera módulos só do cliente escolhido', function () {
    [$a, $b] = [$this->tenant('alfa'), $this->tenant('beta')];
    $this->enable($b, 'operacao', ['agenda']);
    [$ua, $ub] = [$this->member($a, 'tenant_owner'), $this->member($b, 'tenant_owner')];
    ($this->enter)();
    $this->put(PLATFORM."/clientes/{$a->public_id}/modulos", ['modules' => ['operacao'], 'features' => ['operacao.agenda']])->assertSessionHasNoErrors();
    $this->put(PLATFORM."/clientes/{$a->public_id}/modulos", [])->assertSessionHasNoErrors();

    $this->context()->set($a);
    expect($this->access()->allows($ua, 'operacao.agenda.view'))->toBeFalse();
    $this->context()->set($b);
    expect($this->access()->allows($ub, 'operacao.agenda.view'))->toBeTrue();
});

it('detalhe mostra só as pessoas do cliente', function () {
    [$a, $b] = [$this->tenant('alfa'), $this->tenant('beta')];
    [$ua, $ub] = [$this->member($a), $this->member($b)];
    ($this->enter)();
    $this->get(PLATFORM."/clientes/{$a->public_id}")->assertOk()->assertSee($ua->email)->assertDontSee($ub->email);
    $this->get(PLATFORM."/clientes/{$a->id}")->assertNotFound();
    $this->get(PLATFORM.'/clientes?busca=alf')->assertOk()->assertSee('alfa.localhost')->assertDontSee('beta.localhost');
    $this->get(PLATFORM.'/clientes/novo')->assertOk();
});

it('suspende com motivo, bloqueia o acesso e reativa', function () {
    $a = $this->tenant('alfa');
    ($this->enter)();
    $this->put(PLATFORM."/clientes/{$a->public_id}/status", ['status' => 'suspended'])->assertSessionHasErrors('reason');
    $this->put(PLATFORM."/clientes/{$a->public_id}/status", ['status' => 'suspended', 'reason' => 'teste']);
    $this->get($this->url($a, '/login'))->assertNotFound();

    $this->put(PLATFORM."/clientes/{$a->public_id}/status", ['status' => 'active', 'reason' => 'teste']);
    $this->post(PLATFORM.'/logout');
    $this->get($this->url($a, '/login'))->assertOk();
});

it('auditoria da plataforma não mistura eventos de clientes', function () {
    $a = $this->tenant('alfa');
    $this->login($a, $this->member($a, 'tenant_owner'));
    $this->post($this->url($a, '/logout'));
    ($this->enter)();
    $this->put(PLATFORM."/clientes/{$a->public_id}/status", ['status' => 'suspended', 'reason' => 'x']);

    $this->get(PLATFORM.'/auditoria')->assertOk()->assertSee('platform.tenant.status');
    $this->context()->enterPlatform();
    expect(AuditLog::query()->whereNotNull('tenant_id')->count())->toBe(0);
});

it('aceita convite de pessoa nova e permite login', function () {
    $a = $this->tenant('alfa');
    $url = ($this->issue)($a, 'nova@example.com')->url;
    $this->get($url)->assertOk()->assertSee('nova@example.com');
    $this->post($url, ['name' => 'Nova', 'password' => 'curta', 'password_confirmation' => 'curta'])->assertSessionHasErrors('password');
    $data = ['name' => 'Nova', 'password' => 'senha-de-teste-123', 'password_confirmation' => 'senha-de-teste-123'];
    $this->post($url, $data)->assertRedirect($this->url($a, '/login'));

    $user = User::query()->where('email', 'nova@example.com')->sole();
    $this->login($a, $user);
    $this->context()->set($a);
    expect($this->access()->allows($user, 'core.usuario.create'))->toBeTrue();

    $this->post($this->url($a, '/logout'));
    $this->get($url)->assertNotFound();
    $this->post($url, $data)->assertNotFound();
});

it('convite não troca a senha de quem já tem conta', function () {
    $a = $this->tenant('alfa');
    $user = User::factory()->create();
    $url = ($this->issue)($a, $user->email)->url;

    $this->post($url, ['name' => 'X', 'password' => 'outra-senha-123', 'password_confirmation' => 'outra-senha-123'])->assertSessionHasErrors('password');
    $this->context()->run($a, fn () => expect(Membership::query()->count())->toBe(0));
    $this->post($url, ['password' => 'senha-de-teste-123'])->assertSessionHasNoErrors();
    $this->login($a, $user);
});

it('convite de um cliente não vale em outro', function () {
    [$a, $b] = [$this->tenant('alfa'), $this->tenant('beta')];
    $token = basename(($this->issue)($a, 'x@example.com')->url);
    $this->get($this->url($b, "/convite/{$token}"))->assertNotFound();
});

it('convite expirado, cancelado por reenvio ou de vínculo suspenso falha', function () {
    $a = $this->tenant('alfa');
    $first = ($this->issue)($a, 'x@example.com')->url;
    $second = ($this->issue)($a, 'x@example.com')->url;
    $this->get($first)->assertNotFound();
    $this->travel(8)->days();
    $this->get($second)->assertNotFound();
    $this->travelBack();

    $user = $this->member($a, null, null, 'suspended');
    $this->post(($this->issue)($a, $user->email)->url, ['password' => 'senha-de-teste-123'])->assertSessionHasErrors('password');
    $this->context()->run($a, fn () => expect(Membership::query()->sole()->status)->toBe('suspended'));
});

it('reset de senha na plataforma só para quem a administra', function () {
    $other = $this->member($this->tenant('alfa'), 'tenant_owner');
    $this->post(PLATFORM.'/forgot-password', ['email' => $this->admin->email]);
    $this->post(PLATFORM.'/forgot-password', ['email' => $other->email]);

    Notification::assertSentTo($this->admin, ResetPassword::class);
    Notification::assertNotSentTo($other, ResetPassword::class);
});

it('comando cria administrador com senha digitada', function () {
    $this->artisan('platform:admin', ['email' => 'Raiz@Example.com', '--name' => 'Raiz'])
        ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'curta')->assertFailed();
    $this->artisan('platform:admin', ['email' => 'Raiz@Example.com', '--name' => 'Raiz'])
        ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'senha-de-teste-123')->assertSuccessful();

    ($this->enter)(User::query()->where('email', 'raiz@example.com')->sole())->assertRedirect(PLATFORM);
});

it('o middleware da plataforma recusa host de cliente mesmo sem restrição de domínio na rota', function () {
    Route::middleware(['web', 'auth', 'platform'])->get('/so-plataforma', fn () => 'ok');
    $alfa = $this->tenant('alfa');
    $this->login($alfa, $this->member($alfa, 'tenant_owner', $this->admin));
    $this->get($this->url($alfa, '/so-plataforma'))->assertNotFound();
});

it('não grava funcionalidade ligada com o módulo desligado', function () {
    $a = $this->tenant('alfa');
    ($this->enter)();
    $this->put(PLATFORM."/clientes/{$a->public_id}/modulos", ['features' => ['operacao.agenda']])->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('tenant_features', ['tenant_id' => $a->id, 'enabled' => true]);
});
