<?php

use App\Core\Tenancy\Exceptions\CrossTenantAccess;
use App\Core\Tenancy\Exceptions\MissingTenantContext;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\TenantScope;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\RecordTenantJob;
use Tests\Fixtures\Widget;

beforeEach(function () {
    Widget::createTable();
    $this->a = $this->tenant('alfa');
    $this->b = $this->tenant('beta');
    $this->wa = $this->context()->run($this->a, fn () => Widget::query()->create(['label' => 'A']));
    $this->wb = $this->context()->run($this->b, fn () => Widget::query()->create(['label' => 'B']));
});

it('lê somente o próprio tenant', function () {
    $this->context()->set($this->a);

    expect(Widget::query()->pluck('label')->all())->toBe(['A'])
        ->and(Widget::query()->find($this->wb->id))->toBeNull()
        ->and(Widget::query()->count())->toBe(1)
        ->and(Widget::query()->where('label', 'A')->orWhere('label', 'B')->count())->toBe(1);
});

it('falha fechado sem contexto', function () {
    expect(fn () => Widget::query()->get())->toThrow(MissingTenantContext::class)
        ->and(fn () => Widget::query()->create(['label' => 'X']))->toThrow(MissingTenantContext::class);
});

it('não grava em outro tenant', function () {
    $this->context()->set($this->a);
    $other = ['tenant_id' => $this->b->id, 'label' => 'X', 'public_id' => 'x'];

    expect(fn () => Widget::query()->create($other))->toThrow(CrossTenantAccess::class)
        ->and(fn () => Widget::query()->insert($other))->toThrow(CrossTenantAccess::class)
        ->and(fn () => Widget::query()->insert([$other]))->toThrow(CrossTenantAccess::class)
        ->and(fn () => Widget::query()->insert(['label' => 'X']))->toThrow(CrossTenantAccess::class)
        ->and(fn () => Widget::query()->upsert([$other], 'id'))->toThrow(CrossTenantAccess::class)
        ->and(fn () => Widget::query()->update(['tenant_id' => $this->b->id]))->toThrow(CrossTenantAccess::class)
        ->and(fn () => $this->wa->forceFill(['tenant_id' => $this->b->id])->save())->toThrow(CrossTenantAccess::class)
        ->and(fn () => $this->wb->forceFill(['label' => 'Z'])->save())->toThrow(CrossTenantAccess::class)
        ->and(fn () => $this->wb->delete())->toThrow(CrossTenantAccess::class);
});

it('update e delete em massa não alcançam outro tenant', function () {
    $this->context()->set($this->a);
    Widget::query()->update(['label' => 'mudou']);
    Widget::query()->delete();

    expect($this->context()->run($this->b, fn () => Widget::query()->sole()->label))->toBe('B');
});

it('não permite remover o escopo', function () {
    $this->context()->set($this->a);

    expect(fn () => Widget::query()->withoutGlobalScopes()->get())->toThrow(CrossTenantAccess::class)
        ->and(fn () => Widget::query()->withoutGlobalScope(TenantScope::class)->get())->toThrow(CrossTenantAccess::class)
        ->and(fn () => Widget::query()->withoutGlobalScopes([TenantScope::class])->get())->toThrow(CrossTenantAccess::class);
});

it('route model binding devolve 404 para recurso de outro tenant', function () {
    Route::middleware('web')->get('/w/{widget}', fn (Widget $widget) => $widget->label);

    $this->get($this->url($this->a, "/w/{$this->wa->public_id}"))->assertOk()->assertSee('A');
    $this->get($this->url($this->a, "/w/{$this->wb->public_id}"))->assertNotFound();
});

it('ignora tenant_id enviado no body', function () {
    Route::middleware('web')->post('/w', fn () => Widget::query()->create(['label' => request('label')])->tenant_id)
        ->withoutMiddleware(PreventRequestForgery::class);

    $this->post($this->url($this->a, '/w'), ['label' => 'N', 'tenant_id' => $this->b->id])
        ->assertSee((string) $this->a->id);
});

it('o banco recusa vínculo entre tenants', function () {
    $user = $this->member($this->a);
    $membership = $this->context()->run($this->a, fn () => Membership::query()->where('user_id', $user->id)->sole());
    $unit = $this->context()->run($this->b, fn () => Unit::query()->create(['name' => 'Unidade B']));

    expect(fn () => $membership->assignUnit($unit))->toThrow(CrossTenantAccess::class)
        ->and(fn () => DB::table('membership_units')->insert(['tenant_id' => $this->a->id, 'membership_id' => $membership->id, 'unit_id' => $unit->id]))
        ->toThrow(QueryException::class);
});

it('resolve host conhecido e devolve 404 para os demais', function (string $host, int $status) {
    Route::middleware('web')->get('/quem', fn () => 'ok');

    $this->get("http://{$host}/quem")->assertStatus($status);
})->with([
    ['alfa.localhost', 200], ['ALFA.localhost', 200], ['localhost', 404], ['gama.localhost', 404],
    ['www.localhost', 404], ['x.alfa.localhost', 404], ['alfa.localhost.evil.test', 404], ['evilalfa.localhost', 404],
]);

it('tenant suspenso não resolve', function () {
    $this->a->update(['status' => 'suspended']);

    $this->get($this->url($this->a, '/login'))->assertNotFound();
});

it('domínio customizado só vale depois de verificado', function () {
    $domain = TenantDomain::query()->create(['tenant_id' => $this->a->id, 'host' => 'clinica.test', 'type' => 'custom']);
    $this->get('http://clinica.test/login')->assertNotFound();

    $domain->update(['verified_at' => now()]);
    $this->get('http://clinica.test/login')->assertOk();
});

it('restaura o tenant no worker e devolve o contexto anterior', function () {
    config(['queue.default' => 'database']);
    $this->context()->run($this->b, function (): void {
        RecordTenantJob::dispatch();
    });
    $this->context()->set(null);

    $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertSuccessful();

    expect(RecordTenantJob::$seen)->toBe($this->b->id)
        ->and($this->context()->id())->toBeNull();

    RecordTenantJob::$seen = null;
    $this->context()->set($this->a);
    RecordTenantJob::dispatchSync();
    expect(RecordTenantJob::$seen)->toBe($this->a->id)->and($this->context()->id())->toBe($this->a->id);
});

it('job de tenant suspenso não executa', function () {
    config(['queue.default' => 'database']);
    RecordTenantJob::$runs = 0;
    $this->context()->run($this->b, function (): void {
        RecordTenantJob::dispatch();
    });
    $this->b->update(['status' => 'suspended']);

    $this->artisan('queue:work', ['--once' => true, '--tries' => 1]);

    expect(RecordTenantJob::$runs)->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});
