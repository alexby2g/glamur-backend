<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    private function camposPermitidos(): array
    {
        return [
            'nombre_negocio',
            'nombre_corto',
            'slogan',
            'telefono',
            'whatsapp',
            'direccion',
            'mensaje_whatsapp',
            'logo_url',
            'moneda',
            'activo',
        ];
    }

    private function limpiarTexto($valor): string
    {
        return trim((string) ($valor ?? ''));
    }

    private function normalizar(array $datos = []): array
    {
        $base = $this->valoresBase();
        $normalizado = [];

        foreach ($this->camposPermitidos() as $campo) {
            $valor = $datos[$campo] ?? $base[$campo];

            if ($campo === 'activo') {
                $normalizado[$campo] = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($normalizado[$campo] === null) {
                    $normalizado[$campo] = true;
                }

                continue;
            }

            $normalizado[$campo] = $this->limpiarTexto($valor);
        }

        if ($normalizado['nombre_negocio'] === '') {
            $normalizado['nombre_negocio'] = $base['nombre_negocio'];
        }

        if ($normalizado['nombre_corto'] === '') {
            $normalizado['nombre_corto'] = $base['nombre_corto'];
        }

        if ($normalizado['slogan'] === '') {
            $normalizado['slogan'] = $base['slogan'];
        }

        if ($normalizado['mensaje_whatsapp'] === '') {
            $normalizado['mensaje_whatsapp'] = $base['mensaje_whatsapp'];
        }

        if ($normalizado['moneda'] === '') {
            $normalizado['moneda'] = $base['moneda'];
        }

        return $normalizado;
    }

    private function obtenerConfiguracion(): Configuracion
    {
        $configuracion = Configuracion::query()->first();

        if (!$configuracion) {
            $configuracion = Configuracion::create($this->valoresBase());
        }

        return $configuracion;
    }

    private function respuestaConfiguracion(Configuracion $configuracion): array
    {
        return $this->normalizar($configuracion->toArray());
    }

    public function publica(): JsonResponse
    {
        try {
            $configuracion = $this->obtenerConfiguracion();

            return response()->json([
                'configuracion' => $this->respuestaConfiguracion($configuracion),
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'configuracion' => $this->valoresBase(),
            ]);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $configuracion = $this->obtenerConfiguracion();

            return response()->json([
                'configuracion' => $this->respuestaConfiguracion($configuracion),
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'message' => 'No se pudo cargar la configuración.',
                'configuracion' => $this->valoresBase(),
            ], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_negocio' => 'nullable|string|max:150',
            'nombre_corto' => 'nullable|string|max:100',
            'slogan' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:255',
            'mensaje_whatsapp' => 'nullable|string|max:1000',
            'logo_url' => 'nullable|string|max:1000',
            'moneda' => 'nullable|string|max:10',
            'activo' => 'nullable|boolean',
        ], [
            'nombre_negocio.max' => 'El nombre del negocio es demasiado largo.',
            'nombre_corto.max' => 'El nombre corto es demasiado largo.',
            'slogan.max' => 'El slogan es demasiado largo.',
            'telefono.max' => 'El teléfono es demasiado largo.',
            'whatsapp.max' => 'El WhatsApp es demasiado largo.',
            'direccion.max' => 'La dirección es demasiado larga.',
            'mensaje_whatsapp.max' => 'El mensaje de WhatsApp es demasiado largo.',
            'logo_url.max' => 'La URL del logo es demasiado larga.',
            'moneda.max' => 'La moneda es demasiado larga.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $configuracion = $this->obtenerConfiguracion();

            $actual = $this->respuestaConfiguracion($configuracion);
            $nuevosDatos = array_merge($actual, $request->only($this->camposPermitidos()));
            $nuevosDatos = $this->normalizar($nuevosDatos);

            $configuracion->update($nuevosDatos);
            $configuracion->refresh();

            return response()->json([
                'message' => 'Configuración actualizada correctamente.',
                'configuracion' => $this->respuestaConfiguracion($configuracion),
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'message' => 'No se pudo guardar la configuración.',
            ], 500);
        }
    }
}