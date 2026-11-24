<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->string('version', 20);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('module_id')->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
        });

        Schema::create('tenant_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('feature_id')->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_id']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('module', 60)->index();
            $table->string('feature', 120)->nullable();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // Nulo = papel de sistema, compartilhado por todos os tenants.
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            // UNIQUE aceita NULLs repetidos no MySQL; a coluna gerada evita papel de sistema duplicado.
            $table->unsignedBigInteger('tenant_key')->virtualAs('coalesce(tenant_id, 0)');
            $table->string('slug', 60);
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('access_level')->default(0);
            $table->timestamps();
            $table->unique(['tenant_key', 'slug']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            // Sem default: toda permissão precisa de escopo explícito.
            $table->string('scope', 20);
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('membership_roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['membership_id', 'role_id']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('memberships')->cascadeOnDelete();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('email');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'email']);
        });

        Schema::create('resource_acl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 60);
            $table->unsignedBigInteger('resource_id');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('memberships')->cascadeOnDelete();
            $table->unique(['membership_id', 'permission_id', 'resource_type', 'resource_id'], 'resource_acl_grant_unique');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nulo só para eventos da plataforma. Sem FK em actor: o log sobrevive ao usuário.
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 120);
            $table->string('resource_type', 60)->nullable();
            $table->string('resource_id', 60)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('result', 20)->default('success');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'action']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'resource_acl', 'invitations', 'membership_roles', 'role_permissions', 'roles',
            'permissions', 'tenant_features', 'tenant_modules', 'features', 'modules'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
