<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conta criada pelo cliente não usa e-mail global; entra por login dentro do tenant.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->string('login', 40)->nullable();
            $table->unique(['tenant_id', 'login']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('membership_id')->unique();
            $table->string('name');
            // CPF e RG criptografados na aplicação; o hash serve só para unicidade no cliente.
            $table->text('cpf')->nullable();
            $table->char('cpf_hash', 64)->nullable();
            $table->text('rg')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'cpf_hash']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('memberships')->cascadeOnDelete();
        });
    }

    /** Falha se já houver conta sem e-mail: reverter apagaria o acesso dessas pessoas. */
    public function down(): void
    {
        Schema::dropIfExists('employees');

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'login']);
            $table->dropColumn('login');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
