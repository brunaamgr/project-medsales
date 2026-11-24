<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacao_form_fields', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('operacao_form_fields', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
