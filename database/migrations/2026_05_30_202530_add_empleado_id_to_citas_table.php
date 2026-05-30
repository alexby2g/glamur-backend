<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('citas', 'empleado_id')) {
            Schema::table('citas', function (Blueprint $table) {
                $table->foreignId('empleado_id')
                    ->nullable()
                    ->after('cliente_id')
                    ->constrained('empleados')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('citas', 'empleado_id')) {
            Schema::table('citas', function (Blueprint $table) {
                $table->dropForeign(['empleado_id']);
                $table->dropColumn('empleado_id');
            });
        }
    }
};