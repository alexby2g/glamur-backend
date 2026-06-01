<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pagos', 'monto_efectivo')) {
            Schema::table('pagos', function (Blueprint $table) {
                $table->decimal('monto_efectivo', 10, 2)->default(0)->after('monto');
            });
        }

        if (!Schema::hasColumn('pagos', 'monto_qr')) {
            Schema::table('pagos', function (Blueprint $table) {
                $table->decimal('monto_qr', 10, 2)->default(0)->after('monto_efectivo');
            });
        }

        if (!Schema::hasColumn('pagos', 'monto_transferencia')) {
            Schema::table('pagos', function (Blueprint $table) {
                $table->decimal('monto_transferencia', 10, 2)->default(0)->after('monto_qr');
            });
        }
    }

    public function down(): void
    {
        // No eliminamos columnas para proteger datos reales.
    }
};