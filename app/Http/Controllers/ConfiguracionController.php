<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ConfiguracionController extends Controller
{
    private function valoresBase(): array
    {
        return [
            'nombre_negocio' => 'AUREA Beauty Salon',
            'nombre_corto' => 'AUREA Beauty',
            'slogan' => 'Sistema inteligente para salones de belleza',
            'telefono' => '',
            'whatsapp' => '',
            'direccion' => '',
            'mensaje_whatsapp' => 'Hola, quiero información sobre los servicios de AUREA Beauty Salon.',
            'logo_url' => '',
            'moneda' => 'Bs',
            'activo' => true,
        ];
    }

    private function asegurarTablaConfiguraciones(): void
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

        $columnas = [
            'nombre_negocio' => fn (Blueprint $table) => $table->string('nombre_negocio')->default('AUREA Beauty Salon'),
            'nombre_corto' => fn (Blueprint $table) => $table->string('nombre_corto')->default('AUREA Beauty'),
            'slogan' => fn (Blueprint $table) => $table->string('slogan')->nullable(),
            'telefono' => fn (Blueprint $table) => $table->string('telefono')->nullable(),
            'whatsapp' => fn (Blueprint $table) => $table->string('whatsapp')->nullable(),
            'direccion' => fn (Blueprint $table) => $table->string('direccion')->nullable(),
            'mensaje_whatsapp' => fn (Blueprint $table) => $table->text('mensaje_whatsapp')->nullable(),
            'logo_url' => fn (Blueprint $table) => $table->text('logo_url')->nullable(),
            'moneda' => fn (Blueprint $table) => $table->string('moneda', 20)->default('Bs'),
            'activo' => fn (Blueprint $table) => $table->boolean('activo')->default(true),
        ];

        foreach ($columnas as $columna => $callback) {
            if (!Schema::hasColumn('configuraciones', $columna)) {
                Schema::table('configuraciones', function (Blueprint $table) use ($callback) {
                    $callback($table);
                });
            }
        }
    }

    private function obtenerConfiguracion(): Configuracion
    {
        $this->asegurarTablaConfiguraciones();

        $configuracion = Configuracion::query()->first();

        if (!$configuracion) {
            $configuracion = Configuracion::create($this->valoresBase());
        }

        return $configuracion;
    }

    public function publica(): JsonResponse
    {
        $configuracion = $this->obtenerConfiguracion();

        return response()->json([
            'configuracion' => $configuracion,
        ]);
    }

    public function index(): JsonResponse
    {
        $configuracion = $this->obtenerConfiguracion();

        return response()->json([
            'configuracion' => $configuracion,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $configuracion = $this->obtenerConfiguracion();

        $validator = Validator::make($request->all(), [
            'nombre_negocio' => ['required', 'string', 'max:150'],
            'nombre_corto' => ['required', 'string', 'max:80'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'whatsapp' => ['nullable', 'string', 'max:80'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'mensaje_whatsapp' => ['nullable', 'string', 'max:1000'],
            'logo_url' => ['nullable', 'string', 'max:1000'],
            'moneda' => ['nullable', 'string', 'max:20'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre_negocio.required' => 'El nombre del negocio es obligatorio.',
            'nombre_corto.required' => 'El nombre corto es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Hay datos incorrectos en la configuración.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datos = array_merge($this->valoresBase(), $validator->validated());
        $datos['activo'] = (bool) ($datos['activo'] ?? true);

        $configuracion->update($datos);

        return response()->json([
            'message' => 'Configuración actualizada correctamente.',
            'configuracion' => $configuracion->fresh(),
        ]);
    }
}
