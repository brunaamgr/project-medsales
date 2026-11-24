<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Platform\Models\PlatformAdmin;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use App\Modules\Operacao\Forms\AppointmentForm;
use App\Modules\Operacao\Forms\FormSchema;
use App\Modules\Operacao\Models\Appointment;
use App\Modules\Operacao\Models\CatalogItem;
use App\Modules\Operacao\Models\FormField;
use App\Modules\Operacao\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->syncAccess();
    [$this->a, $this->b] = [$this->tenant('alfa'), $this->tenant('beta')];
    $this->enable($this->a, 'operacao', ['agenda', 'pacientes', 'cadastros']);
    $this->enable($this->b, 'operacao', ['agenda', 'pacientes', 'cadastros']);
    $this->owner = $this->member($this->a, 'tenant_owner');
    $this->in = fn ($tenant, Closure $fn) => $this->context()->run($tenant, $fn);
    $this->patient = fn ($tenant, string $name = 'Paciente Sintético', array $extra = []) => ($this->in)($tenant, fn () => Patient::query()->create(['name' => $name, 'created_by' => $this->owner->id, ...$extra]));
    $this->item = fn ($tenant, string $type, string $name, array $extra = []) => ($this->in)($tenant, fn () => CatalogItem::query()->create(['type' => $type, 'name' => $name, 'active' => true, ...$extra]));
    $this->book = fn (array $data = []) => $this->post($this->url($this->a, '/operacao/agenda'), ['date' => '2027-01-10', ...$data]);
    $this->first = fn ($tenant = null) => ($this->in)($tenant ?? $this->a, fn () => Appointment::query()->oldest('id')->first());
    $this->fields = fn (array $changes) => ($this->in)($this->a, function () use ($changes) {
        $input = [];
        foreach (app(FormSchema::class)->fields() as $key => $field) {
            $input[$key] = ['label' => $field->label, 'visible' => $field->visible, 'required' => $field->required, 'position' => $field->position, ...($changes[$key] ?? [])];
        }

        return $input;
    });
});

it('agenda com paciente existente e numera por cliente', function () {
    $p = ($this->patient)($this->a);
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p->public_id, 'start_time' => '09:30', 'notes' => 'texto livre'])->assertSessionHasNoErrors();
    ($this->book)(['patient' => $p->public_id, 'date' => '2027-03-10'])->assertSessionHasNoErrors();

    expect(($this->in)($this->a, fn () => Appointment::query()->orderBy('id')->pluck('number')->all()))->toBe(['CS0001', 'CS0002']);
    $this->get($this->url($this->a, '/operacao/agenda?de=2027-01-10'))->assertOk()->assertSee('09:30')->assertSee('Paciente Sintético');

    $this->post($this->url($this->a, '/logout'));
    $other = $this->member($this->b, 'tenant_owner');
    $pb = ($this->patient)($this->b, 'Do Beta');
    $this->login($this->b, $other);
    $this->post($this->url($this->b, '/operacao/agenda'), ['date' => '2027-01-10', 'patient' => $pb->public_id])->assertSessionHasNoErrors();
    expect(($this->first)($this->b)->number)->toBe('CS0001');
    $this->get($this->url($this->b, '/operacao/agenda?de=2027-01-10'))->assertSee('Do Beta')->assertDontSee('Paciente Sintético');
});

it('cadastra paciente na hora', function () {
    $this->login($this->a, $this->owner);
    ($this->book)()->assertSessionHasErrors('patient');
    ($this->book)(['new_patient_name' => 'Nova Pessoa', 'new_patient_phone' => '11900000000'])->assertSessionHasNoErrors();

    expect(($this->in)($this->a, fn () => Patient::query()->sole()->name))->toBe('Nova Pessoa');
});

it('não usa paciente, lista ou consulta de outro cliente', function () {
    $foreign = ($this->patient)($this->b);
    $mine = ($this->patient)($this->a);
    $professional = ($this->item)($this->b, 'professional', 'Dra. Beta');
    $appointment = ($this->in)($this->b, fn () => tap(new Appointment)->forceFill(['patient_id' => $foreign->id, 'date' => '2027-01-10', 'number' => 'CS0001', 'created_by' => $this->owner->id])->save() ? Appointment::query()->sole() : null);
    $this->login($this->a, $this->owner);

    ($this->book)(['patient' => $foreign->public_id])->assertSessionHasErrors('patient');
    ($this->book)(['patient' => $mine->public_id, 'professional_id' => $professional->id])->assertSessionHasErrors('professional_id');
    $this->get($this->url($this->a, "/operacao/agenda/{$appointment->public_id}"))->assertNotFound();
    $this->put($this->url($this->a, "/operacao/agenda/{$appointment->public_id}"), ['date' => '2027-01-11'])->assertNotFound();
    $this->put($this->url($this->a, "/operacao/agenda/{$appointment->public_id}/cancelar"), ['cancel_reason' => 'x'])->assertNotFound();
    $this->get($this->url($this->a, "/operacao/pacientes/{$foreign->public_id}"))->assertNotFound();
    $this->put($this->url($this->a, "/operacao/cadastros/item/{$professional->public_id}"), ['name' => 'x'])->assertNotFound();
    $this->getJson($this->url($this->a, '/operacao/pacientes/busca?q=Paciente'))->assertJsonCount(1);

    expect(fn () => DB::table('operacao_appointments')->insert(['tenant_id' => $this->a->id, 'public_id' => 'x', 'number' => 'X', 'patient_id' => $foreign->id, 'date' => '2027-01-01', 'created_by' => $this->owner->id]))->toThrow(QueryException::class);
});

it('campo oculto é ignorado e campo obrigatório é exigido', function () {
    $p = ($this->patient)($this->a);
    $this->login($this->a, $this->owner);
    $this->put($this->url($this->a, '/operacao/campos'), ['fields' => ($this->fields)(['price' => ['visible' => 1, 'required' => 1], 'start_time' => ['visible' => 0, 'required' => 1]])])->assertSessionHasNoErrors();

    ($this->book)(['patient' => $p->public_id])->assertSessionHasErrors('price');
    ($this->book)(['patient' => $p->public_id, 'price' => '350.50', 'start_time' => '10:00'])->assertSessionHasNoErrors();
    expect(($this->first)()->getAttribute('price'))->toEqual('350.50')->and(($this->first)()->start_time)->toBeNull();
});

it('cria campo personalizado, valida por tipo e mostra no formulário', function (string $type, mixed $good, mixed $bad) {
    $p = ($this->patient)($this->a);
    $this->login($this->a, $this->owner);
    $this->post($this->url($this->a, '/operacao/campos'), ['label' => 'Campo Extra', 'type' => $type, 'options' => "Sim\nNão"])->assertSessionHasNoErrors();
    $key = ($this->in)($this->a, fn () => FormField::query()->where('custom', true)->sole()->key);

    $this->get($this->url($this->a, '/operacao/agenda/nova'))->assertOk()->assertSee('Campo Extra');
    if ($bad !== null) {
        ($this->book)(['patient' => $p->public_id, 'custom' => [$key => $bad]])->assertSessionHasErrors("custom.{$key}");
    }
    ($this->book)(['patient' => $p->public_id, 'custom' => [$key => $good, 'c_intruso' => 'x']])->assertSessionHasNoErrors();

    expect(($this->first)()->custom_values)->toEqual([$key => $type === 'checkbox' ? true : $good]);
})->with([
    ['text', 'abc', str_repeat('a', 256)], ['textarea', 'abc', str_repeat('a', 2001)], ['number', '1.5', 'abc'], ['money', '10.00', '-1'],
    ['date', '2027-01-01', '01/01/2027'], ['time', '10:00', '25:00'], ['select', 'Sim', 'Talvez'], ['checkbox', '1', 'talvez'],
]);

it('campos de um cliente não aparecem em outro', function () {
    ($this->in)($this->a, fn () => app(FormSchema::class)->addCustom('Só do Alfa', 'text'));
    ($this->in)($this->a, fn () => app(FormSchema::class)->applyPreset('consulta_reserva'));
    $this->login($this->b, $this->member($this->b, 'tenant_owner'));

    $this->get($this->url($this->b, '/operacao/agenda/nova'))->assertOk()->assertDontSee('Só do Alfa')->assertDontSee('Valor da reserva')->assertSee('Horário');
});

it('ocultar campo não apaga o valor gravado', function () {
    $p = ($this->patient)($this->a);
    $field = ($this->in)($this->a, fn () => app(FormSchema::class)->addCustom('Lado', 'text'));
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p->public_id, 'start_time' => '08:00', 'custom' => [$field->key => 'direito']]);
    $this->put($this->url($this->a, '/operacao/campos'), ['fields' => ($this->fields)([$field->key => ['visible' => 0], 'start_time' => ['visible' => 0]])])->assertSessionHasNoErrors();
    $id = ($this->first)()->public_id;
    $this->put($this->url($this->a, "/operacao/agenda/{$id}"), ['patient' => $p->public_id, 'date' => '2027-02-01', 'status' => 'confirmed'])->assertSessionHasNoErrors();

    $saved = ($this->first)();
    expect($saved->custom_values)->toBe([$field->key => 'direito'])->and($saved->time())->toBe('08:00')->and($saved->status)->toBe('confirmed');
});

it('opção removida da lista continua válida em consulta antiga', function () {
    $p = ($this->patient)($this->a);
    $field = ($this->in)($this->a, fn () => app(FormSchema::class)->addCustom('Origem', 'select', "A\nB"));
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p->public_id, 'custom' => [$field->key => 'A']]);
    $this->put($this->url($this->a, '/operacao/campos'), ['fields' => ($this->fields)([$field->key => ['options' => 'B']])]);
    $id = ($this->first)()->public_id;

    $this->put($this->url($this->a, "/operacao/agenda/{$id}"), ['patient' => $p->public_id, 'date' => '2027-01-10', 'custom' => [$field->key => 'A']])->assertSessionHasNoErrors();
    ($this->book)(['patient' => $p->public_id, 'date' => '2027-05-01', 'custom' => [$field->key => 'A']])->assertSessionHasErrors("custom.{$field->key}");
});

it('item desativado some das novas consultas e fica nas antigas', function () {
    $p = ($this->patient)($this->a);
    $item = ($this->item)($this->a, 'professional', 'Dr. Um');
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p->public_id, 'professional_id' => $item->id])->assertSessionHasNoErrors();
    $this->put($this->url($this->a, "/operacao/cadastros/item/{$item->public_id}"), ['name' => 'Dr. Um'])->assertSessionHasNoErrors();

    ($this->book)(['patient' => $p->public_id, 'date' => '2027-06-01', 'professional_id' => $item->id])->assertSessionHasErrors('professional_id');
    $this->put($this->url($this->a, '/operacao/agenda/'.($this->first)()->public_id), ['patient' => $p->public_id, 'date' => '2027-01-10', 'professional_id' => $item->id])->assertSessionHasNoErrors();
});

it('aplica modelo sem perder campos criados', function (string $preset) {
    ($this->in)($this->a, function () use ($preset) {
        $schema = app(FormSchema::class);
        $field = $schema->addCustom('Extra', 'text');
        $schema->applyPreset($preset);
        $visible = array_keys($schema->visible());
        expect($visible)->toContain($field->key)
            ->and(array_diff($visible, [$field->key]))->toEqualCanonicalizing(AppointmentForm::PRESETS[$preset]['visible']);
    });
})->with(array_keys(AppointmentForm::PRESETS));

it('avisa consulta parecida e choque de horário sem bloquear', function () {
    $p = ($this->patient)($this->a);
    $doctor = ($this->item)($this->a, 'professional', 'Dr. Um');
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p->public_id, 'start_time' => '09:00', 'professional_id' => $doctor->id]);

    ($this->book)(['patient' => $p->public_id, 'date' => '2027-01-12'])->assertSessionHas('similar');
    ($this->book)(['new_patient_name' => 'Outra', 'start_time' => '09:15', 'professional_id' => $doctor->id])->assertSessionHas('similar');
    expect(($this->in)($this->a, fn () => [Appointment::query()->count(), Patient::query()->count()]))->toBe([1, 1]);

    ($this->book)(['new_patient_name' => 'Outra', 'start_time' => '09:30', 'professional_id' => $doctor->id])->assertSessionMissing('similar');
    ($this->book)(['patient' => $p->public_id, 'date' => '2027-01-12', 'confirm_similar' => 1])->assertSessionMissing('similar');
    expect(($this->in)($this->a, fn () => Appointment::query()->count()))->toBe(3);
});

it('cancela com motivo, sem apagar, e não edita cancelada', function () {
    $p = ($this->patient)($this->a);
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p->public_id]);
    $url = $this->url($this->a, '/operacao/agenda/'.($this->first)()->public_id);

    $this->put("{$url}/cancelar")->assertSessionHasErrors('cancel_reason');
    $this->put("{$url}/cancelar", ['cancel_reason' => 'pedido'])->assertRedirect();
    expect(($this->first)()->status)->toBe('cancelled');
    $this->put($url, ['patient' => $p->public_id, 'date' => '2027-01-11'])->assertStatus(409);
    $this->get($this->url($this->a, '/operacao/agenda?de=2027-01-10'))->assertDontSee('Paciente Sintético');
    $this->get($this->url($this->a, '/operacao/agenda?de=2027-01-10&status=cancelled'))->assertSee('Paciente Sintético');
});

it('escopo unit: recepção vê e agenda só na própria unidade', function () {
    [$u1, $u2] = ($this->in)($this->a, fn () => [Unit::query()->create(['name' => 'U1']), Unit::query()->create(['name' => 'U2'])]);
    $reception = $this->member($this->a, 'reception');
    ($this->in)($this->a, fn () => Membership::query()->where('user_id', $reception->id)->sole()->assignUnit($u1));
    $p1 = ($this->patient)($this->a, 'Da Unidade Um', ['unit_id' => $u1->id]);
    $p2 = ($this->patient)($this->a, 'Da Unidade Dois', ['unit_id' => $u2->id]);
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p2->public_id])->assertSessionHasErrors('unit');
    ($this->book)(['patient' => $p2->public_id, 'unit' => $u2->public_id])->assertSessionHasNoErrors();
    $other = ($this->first)();
    $this->post($this->url($this->a, '/logout'));

    $this->login($this->a, $reception);
    ($this->book)(['patient' => $p1->public_id])->assertSessionHasNoErrors();
    ($this->book)(['patient' => $p1->public_id, 'date' => '2027-04-01', 'unit' => $u2->public_id])->assertSessionHasErrors('unit');
    ($this->book)(['patient' => $p2->public_id])->assertSessionHasErrors('patient');
    $this->get($this->url($this->a, '/operacao/agenda?de=2027-01-10'))->assertSee('Da Unidade Um')->assertDontSee('Da Unidade Dois');
    $this->get($this->url($this->a, "/operacao/agenda/{$other->public_id}"))->assertForbidden();
    $this->put($this->url($this->a, "/operacao/agenda/{$other->public_id}"), ['date' => '2027-01-10', 'patient' => $p1->public_id])->assertForbidden();
    $this->getJson($this->url($this->a, '/operacao/pacientes/busca?q=Unidade'))->assertJsonCount(1);
    $this->get($this->url($this->a, '/operacao/pacientes'))->assertSee('Da Unidade Um')->assertDontSee('Da Unidade Dois');
});

it('escopo own: profissional só agenda para si', function () {
    $doctor = $this->member($this->a, 'doctor');
    $self = ($this->item)($this->a, 'professional', 'Dra. Própria', ['user_id' => $doctor->id]);
    $colleague = ($this->item)($this->a, 'professional', 'Dr. Colega');
    $this->login($this->a, $doctor);

    ($this->book)(['new_patient_name' => 'Meu Paciente', 'professional_id' => $colleague->id])->assertForbidden();
    expect(($this->in)($this->a, fn () => Patient::query()->count()))->toBe(0);
    ($this->book)(['new_patient_name' => 'Meu Paciente', 'professional_id' => $self->id])->assertSessionHasNoErrors();
    expect(($this->first)()->owner_id)->toBe($doctor->id);
});

it('permissões e funcionalidade protegem as rotas', function () {
    $finance = $this->member($this->a, 'finance');
    $reception = $this->member($this->a, 'reception');
    $this->login($this->a, $finance);
    $this->get($this->url($this->a, '/operacao/agenda'))->assertForbidden();
    $this->get($this->url($this->a, '/operacao/pacientes'))->assertForbidden();
    $this->post($this->url($this->a, '/logout'));

    $this->login($this->a, $reception);
    $this->get($this->url($this->a, '/operacao/campos'))->assertForbidden();
    $this->post($this->url($this->a, '/operacao/campos'), ['label' => 'x', 'type' => 'text'])->assertForbidden();
    $this->get($this->url($this->a, '/operacao/cadastros'))->assertForbidden();
    $this->get($this->url($this->a, '/operacao'))->assertOk()->assertSee('Agenda')->assertDontSee('Personalizar');
    $this->post($this->url($this->a, '/logout'));

    $c = $this->tenant('gama');
    $this->enable($c, 'operacao');
    $this->login($c, $this->member($c, 'tenant_owner'));
    $this->get($this->url($c, '/operacao/agenda'))->assertNotFound();
    $this->get($this->url($c, '/operacao/campos'))->assertNotFound();
});

it('telas abrem para a responsável', function (string $path) {
    $this->login($this->a, $this->owner);
    $this->get($this->url($this->a, $path))->assertOk();
})->with(['/operacao', '/operacao/agenda', '/operacao/agenda/nova', '/operacao/pacientes', '/operacao/cadastros', '/operacao/cadastros/location', '/operacao/campos']);

it('cadastros recusam duplicado, tipo inexistente e usuário de fora', function () {
    $this->login($this->a, $this->owner);
    $outsider = $this->member($this->b);
    $url = $this->url($this->a, '/operacao/cadastros/professional');
    $this->post($url, ['name' => 'Dra. X'])->assertSessionHasNoErrors();
    $this->post($url, ['name' => 'Dra. X'])->assertSessionHasErrors('name');
    $this->post($url, ['name' => 'Dra. Y', 'user_id' => $outsider->id])->assertSessionHasErrors('user_id');
    $this->post($this->url($this->a, '/operacao/cadastros/xpto'), ['name' => 'x'])->assertNotFound();
});

it('observações e campos criados ficam criptografados e fora da auditoria', function () {
    $p = ($this->patient)($this->a);
    $field = ($this->in)($this->a, fn () => app(FormSchema::class)->addCustom('Queixa', 'text'));
    $this->login($this->a, $this->owner);
    ($this->book)(['patient' => $p->public_id, 'notes' => 'dado-sensivel-1', 'custom' => [$field->key => 'dado-sensivel-2']]);

    $row = (array) DB::table('operacao_appointments')->first();
    expect(json_encode($row))->not->toContain('dado-sensivel')
        ->and(($this->in)($this->a, fn () => AuditLog::query()->get()->toJson()))->not->toContain('dado-sensivel')->not->toContain('Paciente Sintético')->toContain('operacao.agenda.created');
});

it('a plataforma personaliza os campos de um cliente sem afetar outro', function () {
    $admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin->id, 'status' => 'active']);
    $this->post('http://admin.localhost/login', ['login' => $admin->email, 'password' => 'senha-de-teste-123']);
    $base = "http://admin.localhost/clientes/{$this->a->public_id}/operacao/campos";

    $this->get($base)->assertOk()->assertSee('Horário');
    $this->get("http://admin.localhost/clientes/{$this->a->public_id}")->assertSee($base);
    $this->post($base, ['label' => 'Da Plataforma', 'type' => 'text'])->assertSessionHasNoErrors();
    $this->post("{$base}/modelo", ['preset' => 'consulta_reserva'])->assertSessionHasNoErrors();
    $this->put($base, ['fields' => ($this->fields)(['price' => ['label' => 'Preço']])])->assertSessionHasNoErrors();

    expect(($this->in)($this->a, fn () => app(FormSchema::class)->fields()['price']->label))->toBe('Preço')
        ->and(($this->in)($this->b, fn () => FormField::query()->count()))->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['tenant_id' => null, 'action' => 'platform.tenant.operacao_fields']);

    $c = $this->tenant('gama');
    $this->get("http://admin.localhost/clientes/{$c->public_id}/operacao/campos")->assertNotFound();
    $this->get("http://admin.localhost/clientes/{$this->a->public_id}/operacao/agenda")->assertNotFound();
});

it('o painel não libera funcionalidade ainda não construída', function () {
    $admin = User::factory()->create();
    PlatformAdmin::query()->create(['user_id' => $admin->id, 'status' => 'active']);
    $this->post('http://admin.localhost/login', ['login' => $admin->email, 'password' => 'senha-de-teste-123']);

    $this->put("http://admin.localhost/clientes/{$this->a->public_id}/modulos", ['modules' => ['operacao'], 'features' => ['operacao.estoque']])->assertSessionHasErrors('features.0');
    $this->get("http://admin.localhost/clientes/{$this->a->public_id}")->assertSee('em breve');
});
