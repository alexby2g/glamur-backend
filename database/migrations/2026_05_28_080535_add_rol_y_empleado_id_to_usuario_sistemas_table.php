<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario_sistemas', function (Blueprint $table) {
            if (!Schema::hasColumn('usuario_sistemas', 'rol')) {
                $table->string('rol')->default('admin')->after('password');
            }

            if (!Schema::hasColumn('usuario_sistemas', 'empleado_id')) {
                $table->foreignId('empleado_id')
                    ->nullable()
                    ->after('rol')
                    ->constrained('empleados')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuario_sistemas', function (Blueprint $table) {
            if (Schema::hasColumn('usuario_sistemas', 'empleado_id')) {
                $table->dropForeign(['empleado_id']);
                $table->dropColumn('empleado_id');
            }

            if (Schema::hasColumn('usuario_sistemas', 'rol')) {
                $table->dropColumn('rol');
            }
        });
    }
};