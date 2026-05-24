<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios_glamur', function (Blueprint $table) {
            $table->id();
            $table->string('grupo')->default('CEJAS Y PESTAÑAS');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::table('servicios_glamur')->insert([
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'CLEAN BROWS',
                'descripcion' => 'Depilación + Visagismo',
                'precio' => 25,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'BROWS PRO',
                'descripcion' => 'Henna + Depilación y Visagismo',
                'precio' => 80,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LAMI BROWS',
                'descripcion' => 'Laminado + Vitaminas + Depilación y Visagismo',
                'precio' => 80,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LASH PERFECT',
                'descripcion' => 'Lifting + Tinte efecto rimel',
                'precio' => 85,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'PERFECT BROWS',
                'descripcion' => 'Laminado + Henna + Depilación + Visagismo',
                'precio' => 135,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'GLOW UP EXPRESS',
                'descripcion' => 'Laminado + Henna + Depilación y Visagismo + Lifting + Tinte efecto rimel',
                'precio' => 220,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'PERFECT EXPRESS',
                'descripcion' => 'Henna + Depilación y Visagismo + Lifting + Tinte efecto rimel',
                'precio' => 165,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LASH & BROWS EXPRESS',
                'descripcion' => 'Laminado + Lifting + Tinte efecto rimel + Vitaminas + Depilación y Visagismo',
                'precio' => 165,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'RETOQUE BROWS PRO',
                'descripcion' => 'Henna',
                'precio' => 40,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios_glamur');
    }
};