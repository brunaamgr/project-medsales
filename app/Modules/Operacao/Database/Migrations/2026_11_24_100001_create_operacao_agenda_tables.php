<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacao_patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'name']);
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });

        // Listas de cada cliente: profissionais, tipos, locais, convênios e procedimentos.
        Schema::create('operacao_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('type', 30);
            $table->string('name');
            $table->decimal('default_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('default_duration')->nullable();
            // Profissional ligado a um usuário: é o que dá sentido ao escopo "own".
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'type', 'name']);
        });

        // Configuração de formulário por cliente. Campo padrão sem linha usa o modelo essencial.
        Schema::create('operacao_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('form', 30);
            $table->string('key', 40);
            $table->boolean('custom')->default(false);
            $table->string('label', 80);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->boolean('visible')->default(true);
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'form', 'key']);
        });

        Schema::create('operacao_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 30);
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('operacao_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('number', 20);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            // Data e hora civis do local de atendimento, não instantes UTC.
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->date('booked_on')->nullable();
            $table->string('status', 20)->default('scheduled');
            foreach (['professional_id', 'service_type_id', 'attendance_type_id', 'location_id', 'insurance_id', 'procedure_id'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
                $table->foreign(['tenant_id', $column], "operacao_appt_{$column}_fk")->references(['tenant_id', 'id'])->on('operacao_catalog_items')->restrictOnDelete();
            }
            $table->decimal('price', 12, 2)->nullable();
            $table->string('deposit_status', 20)->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            // Criptografados na aplicação: podem conter dado de saúde.
            $table->text('notes')->nullable();
            $table->text('custom_values')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'date']);
            $table->foreign(['tenant_id', 'patient_id'])->references(['tenant_id', 'id'])->on('operacao_patients')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['appointments', 'counters', 'form_fields', 'catalog_items', 'patients'] as $table) {
            Schema::dropIfExists("operacao_{$table}");
        }
    }
};
