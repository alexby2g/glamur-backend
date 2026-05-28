<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar empleado_id a la tabla citas.
     * Es nullable para no romper las citas antiguas que ya existen.
     */
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            if (!Schema::hasColumn('citas', 'empleado_id')) {
                $table->foreignId('empleado_id')
                    ->nullable()
                    ->after('cliente_id')
                    ->constrained('empleados')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Quitar empleado_id si se revierte esta migración.
     */
    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            if (Schema::hasColumn('citas', 'empleado_id')) {
                $table->dropForeign(['empleado_id']);
                $table->dropColumn('empleado_id');
            }
        });
    }
};