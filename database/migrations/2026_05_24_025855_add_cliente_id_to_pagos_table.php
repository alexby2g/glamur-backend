<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {

            // 🔥 RELACIÓN CON CLIENTES
            $table->foreignId('cliente_id')
                ->nullable()
                ->after('id')
                ->constrained('clientes')
                ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {

            // 🔥 ELIMINAR FOREIGN KEY
            $table->dropForeign(['cliente_id']);

            // 🔥 ELIMINAR COLUMNA
            $table->dropColumn('cliente_id');

        });
    }
};