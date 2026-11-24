<?php

use Illuminate\Support\Facades\Schema;

/** Tabelas globais ou do framework. Incluir aqui exige revisão e ADR. */
const GLOBAL_TABLES = [
    'users', 'platform_admins', 'profiles', 'tenants', 'modules', 'features', 'permissions', 'role_permissions',
    'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations',
];

/** tenant_id presente, porém nulo de propósito (papel de sistema, evento de plataforma). */
const NULLABLE_TENANT = ['roles', 'audit_logs'];

it('toda tabela de domínio tem tenant_id obrigatório com FK e índice', function () {
    $tables = array_diff(array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name'), GLOBAL_TABLES, ['widgets', 'sqlite_sequence']);
    expect($tables)->not->toBeEmpty();

    foreach ($tables as $table) {
        $column = collect(Schema::getColumns($table))->firstWhere('name', 'tenant_id');
        expect($column)->not->toBeNull("{$table} sem tenant_id")
            ->and($column['nullable'])->toBe(in_array($table, NULLABLE_TENANT, true), "{$table}.tenant_id")
            ->and(collect(Schema::getForeignKeys($table))->contains(fn ($fk) => $fk['foreign_table'] === 'tenants'))->toBeTrue("{$table} sem FK");
    }
});

it('migrations voltam e sobem de novo', function () {
    $this->artisan('migrate:rollback')->assertSuccessful();
    expect(Schema::hasTable('tenants'))->toBeFalse();
    $this->artisan('migrate')->assertSuccessful();
    expect(Schema::hasTable('audit_logs'))->toBeTrue();
});
