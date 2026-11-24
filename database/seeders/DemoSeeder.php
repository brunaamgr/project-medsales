<?php

namespace Database\Seeders;

use App\Core\Modules\Entitlements;
use App\Core\Permissions\AccessResolver;
use App\Core\Permissions\AccessSynchronizer;
use App\Core\Platform\Models\PlatformAdmin;
use App\Core\Platform\SyncEntitlements;
use App\Core\Roles\Models\Role;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantDomain;
use App\Core\Tenancy\TenantContext;
use App\Core\Users\Models\Membership;
use App\Core\Users\Models\Unit;
use App\Core\Users\Models\User;
use App\Modules\Operacao\Actions\SaveAppointment;
use App\Modules\Operacao\Forms\FormSchema;
use App\Modules\Operacao\Models\Appointment;
use App\Modules\Operacao\Models\CatalogItem;
use App\Modules\Operacao\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Cenário local próximo do real: configuração inspirada no legado (docs/modulos/operacao.md),
 * pessoas e pacientes sempre sintéticos. Nunca roda em produção.
 *
 * Só configura o cliente na primeira vez: rodar de novo não desfaz ajustes feitos nas telas.
 */
class DemoSeeder extends Seeder
{
    /** Credencial sintética, só para o ambiente local. */
    public const PASSWORD = 'demo-local-medsales';

    /** Clientes da demonstração anterior, renomeados para preservar o que já foi testado. */
    private const RENAMED = ['alfa' => 'pinhabel', 'beta' => 'mychelleamaral'];

    public function run(AccessSynchronizer $access, TenantContext $context, SyncEntitlements $entitlements): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('DemoSeeder só roda em local/testing.');
        }

        $access->sync();

        $admin = $this->user('admin@medsales.example', 'Admin Demo');
        PlatformAdmin::query()->firstOrCreate(['user_id' => $admin->id], ['status' => 'active']);

        foreach (self::clients() as $slug => $client) {
            DB::transaction(function () use ($slug, $client, $context, $entitlements): void {
                $this->client($slug, $client, $context, $entitlements);
            });
        }
    }

    /** @return array<string, array{name: string, modules: list<string>, features: list<string>, preset: string, catalogs: array<string, list<string>>, fields: list<array{string, string, string}>, appointment: array<string, mixed>}> */
    private static function clients(): array
    {
        return [
            'pinhabel' => [
                'name' => 'Clínica Pinhabel',
                'modules' => ['operacao'],
                'features' => ['operacao.agenda'],
                'preset' => 'consulta_reserva',
                'catalogs' => [
                    'attendance_type' => ['Presencial', 'Online', 'Retorno', 'Cortesia'],
                    'service_type' => ['Avaliação', 'Primeira consulta', 'Pós-operatório'],
                    'location' => ['Consultório 1', 'Consultório 2'],
                    'insurance' => ['Particular', 'Convênio Demo'],
                ],
                'fields' => [
                    ['Consultora', 'select', "Consultora Demo 1\nConsultora Demo 2"],
                    ['Origem (quem transferiu)', 'text', ''],
                ],
                'appointment' => ['booked_on' => true, 'attendance_type_id' => 'attendance_type', 'service_type_id' => 'service_type', 'location_id' => 'location', 'price' => '500.00', 'deposit_status' => 'paid', 'deposit_amount' => '200.00'],
            ],
            'mychelleamaral' => [
                'name' => 'Dra. Mychelle Amaral',
                'modules' => ['operacao', 'acompanhamento'],
                'features' => ['operacao.agenda', 'acompanhamento.pacientes', 'acompanhamento.diario', 'acompanhamento.medidas', 'acompanhamento.mensagens', 'acompanhamento.plano'],
                'preset' => 'horario_duracao',
                'catalogs' => [
                    'service_type' => ['Clínica', 'Check-Up', 'Procedimento', 'Acompanhamento'],
                    'attendance_type' => ['Presencial', 'Online'],
                    'procedure' => ['Procedimento Demo 1', 'Procedimento Demo 2'],
                ],
                'fields' => [
                    ['Nota fiscal', 'select', "Sim\nNão"],
                    ['Status da NF', 'select', "Pendente\nEmitida"],
                    ['Pacote vinculado', 'text', ''],
                ],
                'appointment' => ['booked_on' => true, 'start_time' => true, 'duration_minutes' => 40, 'service_type_id' => 'service_type', 'attendance_type_id' => 'attendance_type', 'price' => '600.00', 'deposit_status' => 'none'],
            ],
        ];
    }

    /** @param array{name: string, modules: list<string>, features: list<string>, preset: string, catalogs: array<string, list<string>>, fields: list<array{string, string, string}>, appointment: array<string, mixed>} $client */
    private function client(string $slug, array $client, TenantContext $context, SyncEntitlements $entitlements): void
    {
        if (Tenant::query()->where('slug', $slug)->exists()) {
            return;
        }

        $tenant = $this->rename($slug, $client['name'])
            ?? Tenant::query()->create(['name' => $client['name'], 'slug' => $slug, 'subdomain' => $slug]);

        TenantDomain::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'type' => 'subdomain'],
            ['host' => $slug.'.'.config()->string('tenancy.base_domain'), 'is_primary' => true, 'verified_at' => now()],
        );
        $entitlements->handle($tenant, $client['modules'], $client['features']);

        $context->run($tenant, function () use ($slug, $client): void {
            $unit = Unit::query()->firstOrCreate(['name' => 'Principal']);
            $owner = $this->member("owner@{$slug}.example", 'Responsável Demo', 'tenant_owner', $unit);
            $this->member("recepcao@{$slug}.example", 'Recepção Demo', 'reception', $unit);
            $doctor = $this->member("medico@{$slug}.example", 'Profissional Demo', 'doctor', $unit);

            $schema = app(FormSchema::class);
            $schema->applyPreset($client['preset']);
            foreach ($client['fields'] as [$label, $type, $options]) {
                $schema->addCustom($label, $type, $options);
            }

            $items = ['professional' => CatalogItem::query()->firstOrCreate(['type' => 'professional', 'name' => 'Profissional Demo'], ['user_id' => $doctor->id, 'active' => true])->id];
            foreach ($client['catalogs'] as $type => $names) {
                foreach ($names as $name) {
                    $item = CatalogItem::query()->firstOrCreate(['type' => $type, 'name' => $name], ['active' => true]);
                    $items[$type] ??= $item->id;
                }
            }

            $this->appointments($client['appointment'], $items, $unit, $owner, $doctor);
        });
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, int>  $items
     */
    private function appointments(array $template, array $items, Unit $unit, User $owner, User $doctor): void
    {
        if (Appointment::query()->exists()) {
            return;
        }

        app(AccessResolver::class)->flush();
        app(Entitlements::class)->flush();
        $today = now(config()->string('app.display_timezone'));

        foreach (range(1, 6) as $i) {
            $patient = Patient::query()->create([
                'name' => sprintf('Paciente Teste %02d', $i),
                'phone' => sprintf('(00) 00000-%04d', $i),
                'unit_id' => $unit->id,
                'owner_id' => $doctor->id,
                'created_by' => $owner->id,
            ]);

            $data = ['date' => $today->copy()->addDays(intdiv($i - 1, 3))->format('Y-m-d'), 'status' => 'scheduled', 'professional_id' => $items['professional']];
            foreach ($template as $key => $value) {
                $data[$key] = match (true) {
                    $key === 'booked_on' => $today->format('Y-m-d'),
                    $key === 'start_time' => sprintf('%02d:00', 8 + $i),
                    str_ends_with($key, '_id') => $items[$value],
                    default => $value,
                };
            }

            app(SaveAppointment::class)->handle(new Appointment, $patient, $unit->id, $data, $owner);
        }
    }

    private function rename(string $slug, string $name): ?Tenant
    {
        $old = array_search($slug, self::RENAMED, true);
        $tenant = $old === false ? null : Tenant::query()->where('slug', $old)->first();

        if ($tenant === null) {
            return null;
        }

        $tenant->update(['name' => $name, 'slug' => $slug, 'subdomain' => $slug]);

        foreach (['owner', 'recepcao'] as $login) {
            User::query()->where('email', "{$login}@{$old}.example")->first()?->update(['email' => "{$login}@{$slug}.example"]);
        }

        return $tenant;
    }

    private function member(string $email, string $name, string $role, Unit $unit): User
    {
        $user = $this->user($email, $name);
        $membership = Membership::query()->firstOrCreate(['user_id' => $user->id], ['status' => 'active', 'joined_at' => now()]);
        $membership->assignRole(Role::system($role));
        $membership->assignUnit($unit);

        return $user;
    }

    private function user(string $email, string $name): User
    {
        return User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD]);
    }
}
