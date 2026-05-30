<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega foto de perfil para administradores y empleados.
     */
    public function up(): void
    {
        Schema::table('usuario_sistemas', function (Blueprint $table) {
            if (!Schema::hasColumn('usuario_sistemas', 'foto_perfil')) {
                $table->longText('foto_perfil')->nullable()->after('empleado_id');
            }
        });
    }

    /**
     * Revierte la columna foto_perfil.
     */
    public function down(): void
    {
        Schema::table('usuario_sistemas', function (Blueprint $table) {
            if (Schema::hasColumn('usuario_sistemas', 'foto_perfil')) {
                $table->dropColumn('foto_perfil');
            }
        });
    }
};