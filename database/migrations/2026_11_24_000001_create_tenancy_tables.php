<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->string('subdomain', 63)->unique();
            $table->string('status', 20)->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('host', 253)->unique();
            $table->string('type', 20)->default('subdomain');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            // Alvo das FKs compostas: o banco recusa vínculo entre tenants.
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('invited');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('membership_units', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('unit_id');
            $table->primary(['membership_id', 'unit_id']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('memberships')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['membership_units', 'memberships', 'units', 'tenant_domains', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
