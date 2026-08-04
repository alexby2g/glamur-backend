<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->decimal('fondo_inicial', 12, 2)->default(0);
            $table->decimal('total_efectivo', 12, 2)->default(0);
            $table->decimal('total_qr', 12, 2)->default(0);
            $table->decimal('total_transferencia', 12, 2)->default(0);
            $table->decimal('total_otros', 12, 2)->default(0);
            $table->decimal('total_cobrado', 12, 2)->default(0);
            $table->decimal('efectivo_esperado', 12, 2)->default(0);
            $table->decimal('efectivo_contado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('abierto_por')->nullable()->constrained('usuario_sistemas')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('usuario_sistemas')->nullOnDelete();
            $table->timestamp('abierta_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_caja');
    }
};
