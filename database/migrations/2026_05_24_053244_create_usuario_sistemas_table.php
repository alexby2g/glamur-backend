<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar migración.
     */
    public function up(): void
    {
        Schema::create('usuario_sistemas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            $table->string('usuario')->unique();
            $table->string('password');

            $table->string('token', 100)->nullable()->unique();

            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_acceso')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Revertir migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_sistemas');
    }
};