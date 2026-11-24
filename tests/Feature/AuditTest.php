<?php

use App\Core\Audit\AuditLogger;
use App\Core\Audit\Models\AuditLog;

beforeEach(function () {
    $this->syncAccess();
    $this->a = $this->tenant('alfa');
    $this->b = $this->tenant('beta');
});

it('é append-only', function () {
    $this->context()->set($this->a);
    app(AuditLogger::class)->record('teste');
    $log = AuditLog::query()->sole();

    expect(fn () => $log->forceFill(['action' => 'x'])->save())->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and(fn () => AuditLog::query()->update(['action' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => AuditLog::query()->delete())->toThrow(LogicException::class)
        ->and(AuditLog::query()->sole()->action)->toBe('teste');
});

it('mascara dados sensíveis, inclusive aninhados', function () {
    expect(AuditLogger::mask(['password' => 'x', 'paciente' => ['cpf' => '1', 'status' => 'ok'], 'full_name' => 'n']))
        ->toBe(['password' => '[mascarado]', 'paciente' => ['cpf' => '[mascarado]', 'status' => 'ok'], 'full_name' => '[mascarado]']);
});

it('registra evento de plataforma sem tenant', function () {
    app(AuditLogger::class)->record('platform.teste');
    $this->assertDatabaseHas('audit_logs', ['action' => 'platform.teste', 'tenant_id' => null]);
});

it('mostra só o próprio tenant e exige permissão', function () {
    $this->context()->run($this->b, fn () => app(AuditLogger::class)->record('segredo.do.beta'));
    $this->login($this->a, $this->member($this->a, 'tenant_owner'));
    $this->get($this->url($this->a, '/auditoria'))->assertOk()->assertSee('auth.login')->assertDontSee('segredo.do.beta');

    $this->post($this->url($this->a, '/logout'));
    $this->login($this->a, $this->member($this->a, 'reception'));
    $this->get($this->url($this->a, '/auditoria'))->assertForbidden();

    $this->context()->run($this->a, fn () => expect(AuditLog::query()->where('action', 'access.denied')->count())->toBe(1));
});
