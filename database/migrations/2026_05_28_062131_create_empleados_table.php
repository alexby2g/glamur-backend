<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crear tabla de empleados / personal del salón.
     */
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            $table->string('telefono')->nullable();
            $table->string('ci')->nullable();
            $table->string('email')->nullable();

            $table->string('cargo')->nullable();
            $table->string('especialidad')->nullable();

            $table->decimal('comision_porcentaje', 5, 2)->default(0);
            $table->decimal('salario_base', 10, 2)->default(0);

            $table->string('direccion')->nullable();
            $table->date('fecha_ingreso')->nullable();

            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('nombre');
            $table->index('telefono');
            $table->index('activo');
            $table->index('especialidad');
        });
    }

    /**
     * Eliminar tabla de empleados si se revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};