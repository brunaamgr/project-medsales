<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Auth\SessionGuardian;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->syncAccess();
    $this->a = $this->tenant('alfa');
    $this->b = $this->tenant('beta');
});

it('exige login e não tem cadastro público', function () {
    $this->get($this->url($this->a))->assertRedirect($this->url($this->a, '/login'));
    $this->get($this->url($this->a, '/register'))->assertNotFound();
    $this->get($this->url($this->a, '/login'))->assertOk()->assertSee('Alfa');
});

it('entra, vê só os próprios módulos e sai', function () {
    $this->enable($this->a, 'precificacao', ['tabela']);
    $owner = $this->member($this->a, 'tenant_owner');
    $this->login($this->a, $owner);

    $this->get($this->url($this->a))->assertOk()->assertSee('Precificação')->assertDontSee('Acompanhamento');
    $this->post($this->url($this->a, '/logout'))->assertRedirect();
    $this->get($this->url($this->a))->assertRedirect($this->url($this->a, '/login'));
});

it('recusa com a mesma mensagem quem não pode entrar', function (Closure $prepare) {
    $user = $prepare->call($this);

    $this->post($this->url($this->a, '/login'), ['login' => $user->email, 'password' => 'senha-de-teste-123'])
        ->assertSessionHasErrors(['login' => 'Login ou senha inválidos.']);
    $this->assertGuest();
})->with([
    'sem vínculo' => [fn () => User::factory()->create()],
    'vínculo em outro tenant' => [fn () => $this->member($this->b, 'tenant_owner')],
    'vínculo suspenso' => [fn () => $this->member($this->a, 'tenant_owner', null, 'suspended')],
    'convite pendente' => [fn () => $this->member($this->a, 'tenant_owner', null, 'invited')],
    'usuário suspenso' => [function () {
        $user = $this->member($this->a, 'tenant_owner');
        $user->forceFill(['status' => 'suspended'])->save();

        return $user;
    }],
    'e-mail inexistente' => [fn () => User::factory()->make()],
]);

it('sessão de um tenant não vale em outro, mesmo com vínculo nos dois', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->member($this->b, 'tenant_owner', $user);
    $this->login($this->a, $user);

    $this->get($this->url($this->b))->assertRedirect($this->url($this->b, '/login'));
    $this->assertGuest();
});

it('suspender o vínculo derruba a sessão no request seguinte', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->login($this->a, $user);
    $this->context()->run($this->a, fn () => Membership::query()->sole()->update(['status' => 'suspended']));

    $this->get($this->url($this->a))->assertRedirect($this->url($this->a, '/login'));
    $this->assertGuest();
});

it('encerra a sessão no limite absoluto', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->login($this->a, $user);
    $this->withSession([SessionGuardian::STARTED => now()->subHours(13)->getTimestamp()]);

    $this->get($this->url($this->a))->assertRedirect($this->url($this->a, '/login'));
});

it('limita tentativas de login', function () {
    $user = $this->member($this->a, 'tenant_owner');

    foreach (range(1, 5) as $ignored) {
        $this->post($this->url($this->a, '/login'), ['login' => $user->email, 'password' => 'errada']);
    }

    $this->post($this->url($this->a, '/login'), ['login' => $user->email, 'password' => 'senha-de-teste-123'])->assertTooManyRequests();
});

it('usa Argon2id', function () {
    expect(User::factory()->create()->password)->toStartWith('$argon2id$');
});

it('reset só envia e-mail para vínculo ativo e responde igual', function () {
    Notification::fake();
    $in = $this->member($this->a);
    $out = $this->member($this->b);

    foreach ([$in->email, $out->email, 'ninguem@example.com'] as $email) {
        $this->post($this->url($this->a, '/forgot-password'), ['email' => $email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }

    Notification::assertSentTo($in, ResetPassword::class);
    Notification::assertNotSentTo($out, ResetPassword::class);
});

it('redefinir senha exige 12 caracteres e revoga sessões', function () {
    $user = $this->member($this->a, 'tenant_owner');
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => 1]);
    $payload = fn (string $password) => ['token' => Password::createToken($user), 'email' => $user->email, 'password' => $password, 'password_confirmation' => $password];

    $this->post($this->url($this->a, '/reset-password'), $payload('curta'))->assertSessionHasErrors('password');
    $this->post($this->url($this->a, '/reset-password'), $payload('nova-senha-segura-1'))->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('audita login e falha sem gravar e-mail ou senha', function () {
    $user = $this->member($this->a, 'tenant_owner');
    $this->post($this->url($this->a, '/login'), ['login' => $user->email, 'password' => 'errada-xyz']);
    $this->login($this->a, $user);
    $this->context()->set($this->a);

    $logs = AuditLog::query()->get();
    expect($logs->pluck('result')->all())->toBe(['failed', 'success'])
        ->and($logs->toJson())->not->toContain($user->email)->not->toContain('errada-xyz');
});

it('envia cabeçalhos de segurança', function () {
    $response = $this->get($this->url($this->a, '/login'));

    $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Security-Policy'))->not->toContain('unsafe');
});
