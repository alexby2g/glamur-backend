<?php

namespace App\Http\Controllers;

use App\Models\UsuarioSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SetupController extends Controller
{
    public function instalar(Request $request)
    {
        $clave = $request->get('key');

        if ($clave !== 'GLAMUR_SETUP_2026_ALEX') {
            return response()->json([
                'message' => 'No autorizado.'
            ], 403);
        }

        try {
            Artisan::call('migrate', [
                '--force' => true
            ]);

            $migracion = Artisan::output();

            $usuario = UsuarioSistema::updateOrCreate(
                [
                    'usuario' => 'Cristina'
                ],
                [
                    'nombre' => 'Administrador Glamur',
                    'password' => '74716354',
                    'activo' => true
                ]
            );

            return response()->json([
                'message' => 'Instalación completada correctamente.',
                'migracion' => $migracion,
                'usuario' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'usuario' => $usuario->usuario,
                    'activo' => $usuario->activo
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en setup Glamur', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al ejecutar instalación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}