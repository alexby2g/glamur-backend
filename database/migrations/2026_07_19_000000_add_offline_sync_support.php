<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $syncTables = ['clientes', 'empleados', 'servicios', 'citas', 'pagos'];

    public function up(): void
    {
        foreach ($this->syncTables as $tableName) {
            if (!Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable()->unique();
                });
            }

            DB::table($tableName)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                });
        }

        if (!Schema::hasColumn('servicios', 'deleted_at')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('sync_operations')) {
            Schema::create('sync_operations', function (Blueprint $table) {
                $table->id();
                $table->uuid('operation_id')->unique();
                $table->foreignId('usuario_sistema_id')->nullable()->constrained('usuario_sistemas')->nullOnDelete();
                $table->string('entity_type', 40);
                $table->uuid('entity_uuid');
                $table->string('action', 20);
                $table->json('response')->nullable();
                $table->timestamps();
                $table->index(['entity_type', 'entity_uuid']);
            });
        }

        if (!Schema::hasTable('usuario_sistema_tokens')) {
            Schema::create('usuario_sistema_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('usuario_sistema_id')->constrained('usuario_sistemas')->cascadeOnDelete();
                $table->uuid('device_id');
                $table->string('device_name')->nullable();
                $table->char('token_hash', 64)->unique();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['usuario_sistema_id', 'device_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_sistema_tokens');
        Schema::dropIfExists('sync_operations');

        if (Schema::hasColumn('servicios', 'deleted_at')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        foreach (array_reverse($this->syncTables) as $tableName) {
            if (Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
