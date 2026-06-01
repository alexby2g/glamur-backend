<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('pagos', 'monto_efectivo')) {
                $table->decimal('monto_efectivo', 10, 2)->default(0)->after('monto');
            }

            if (!Schema::hasColumn('pagos', 'monto_qr')) {
                $table->decimal('monto_qr', 10, 2)->default(0)->after('monto_efectivo');
            }

            if (!Schema::hasColumn('pagos', 'monto_transferencia')) {
                $table->decimal('monto_transferencia', 10, 2)->default(0)->after('monto_qr');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'monto_transferencia')) {
                $table->dropColumn('monto_transferencia');
            }

            if (Schema::hasColumn('pagos', 'monto_qr')) {
                $table->dropColumn('monto_qr');
            }

            if (Schema::hasColumn('pagos', 'monto_efectivo')) {
                $table->dropColumn('monto_efectivo');
            }
        });
    }
};