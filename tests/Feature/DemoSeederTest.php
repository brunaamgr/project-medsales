<?php

use App\Core\Platform\SyncEntitlements;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Users\Models\User;
use App\Modules\Operacao\Forms\FormSchema;
use App\Modules\Operacao\Models\Appointment;
use App\Modules\Operacao\Models\Patient;
use Database\Seeders\DemoSeeder;

it('monta Pinhabel e Mychelle com configurações diferentes', function () {
    $this->seed(DemoSeeder::class);
    [$p, $m] = [Tenant::query()->where('slug', 'pinhabel')->sole(), Tenant::query()->where('slug', 'mychelleamaral')->sole()];
    $labels = fn (Tenant $t) => $this->context()->run($t, fn () => array_column(array_map(fn ($f) => ['l' => $f->label], app(FormSchema::class)->visible()), 'l'));

    expect($labels($p))->toContain('Consultora', 'Valor da reserva (R$)')->not->toContain('Horário', 'Nota fiscal')
        ->and($labels($m))->toContain('Horário', 'Duração (minutos)', 'Nota fiscal')->not->toContain('Consultora', 'Local')
        ->and($this->context()->run($p, fn () => Appointment::query()->count()))->toBe(6);
});

it('rodar de novo não duplica nem desfaz ajustes', function () {
    $this->seed(DemoSeeder::class);
    $tenant = Tenant::query()->where('slug', 'pinhabel')->sole();
    app(SyncEntitlements::class)->handle($tenant, [], []);
    $this->seed(DemoSeeder::class);

    $this->context()->run($tenant, function () {
        expect(app(SyncEntitlements::class)->snapshot()['modules'])->toBe([])
            ->and(Patient::query()->count())->toBe(6);
    });
    expect(Tenant::query()->count())->toBe(2);
});

it('renomeia o cliente da demonstração anterior e mantém os dados', function () {
    $this->syncAccess();
    $old = $this->tenant('alfa');
    $user = $this->member($old, 'tenant_owner', User::factory()->create(['email' => 'owner@alfa.example']));
    $this->context()->run($old, fn () => Patient::query()->create(['name' => 'Já Existia', 'created_by' => $user->id]));
    $this->seed(DemoSeeder::class);

    expect($old->refresh()->subdomain)->toBe('pinhabel')
        ->and($user->refresh()->email)->toBe('owner@pinhabel.example')
        ->and($this->context()->run($old, fn () => Patient::query()->where('name', 'Já Existia')->exists()))->toBeTrue()
        ->and(Tenant::query()->whereIn('slug', ['alfa', 'beta'])->exists())->toBeFalse();
});

it('usa só pacientes e e-mails sintéticos', function () {
    $this->seed(DemoSeeder::class);

    expect(User::query()->where('email', 'not like', '%.example')->count())->toBe(0);
    foreach (Tenant::query()->get() as $tenant) {
        $this->context()->run($tenant, fn () => expect(Patient::query()->where('name', 'not like', 'Paciente Teste %')->count())->toBe(0));
    }
});
