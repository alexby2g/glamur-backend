<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->date('fecha');
            $table->time('hora');
            $table->string('servicio');
            $table->decimal('precio', 10, 2)->default(0);
            $table->enum('estado', ['pendiente', 'concluida', 'cancelada'])->default('pendiente');
            $table->enum('estado_pago', ['pendiente', 'pagado'])->default('pendiente');
            $table->string('metodo_pago')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
