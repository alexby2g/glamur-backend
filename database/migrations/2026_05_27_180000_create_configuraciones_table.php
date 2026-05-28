<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuraciones')) {
            Schema::create('configuraciones', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_negocio')->default('AUREA Beauty Salon');
                $table->string('nombre_corto')->default('AUREA Beauty');
                $table->string('slogan')->nullable();
                $table->string('telefono')->nullable();
                $table->string('whatsapp')->nullable();
                $table->string('direccion')->nullable();
                $table->text('mensaje_whatsapp')->nullable();
                $table->text('logo_url')->nullable();
                $table->string('moneda', 20)->default('Bs');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
