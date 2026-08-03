<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_sistema_id')->nullable()->index();
            $table->string('usuario_nombre')->nullable();
            $table->string('usuario_rol', 30)->nullable()->index();
            $table->string('accion', 30)->index();
            $table->string('metodo', 10);
            $table->string('modulo', 80)->index();
            $table->string('ruta');
            $table->string('entidad_id')->nullable()->index();
            $table->json('datos')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('dispositivo', 500)->nullable();
            $table->unsignedSmallInteger('codigo_respuesta')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'modulo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
