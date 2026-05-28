<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRolSistema
{
    private array $rolesPermitidos = [
        'admin',
        'empleado',
    ];

    private function normalizarRol($rol): string
    {
        $rol = strtolower(trim((string) $rol));

        return in_array($rol, $this->rolesPermitidos, true)
            ? $rol
            : 'empleado';
    }

    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        /*
        |--------------------------------------------------------------------------
        | Este middleware debe usarse después de VerificarTokenSistema
        |--------------------------------------------------------------------------
        | VerificarTokenSistema ya coloca estos datos:
        |
        | usuario_sistema
        | usuario_rol
        | usuario_empleado_id
        | usuario_es_admin
        | usuario_es_empleado
        */

        $usuario = $request->attributes->get('usuario_sistema');
        $rolUsuario = $this->normalizarRol(
            $request->attributes->get('usuario_rol')
        );

        if (!$usuario) {
            return response()->json([
                'message' => 'No autorizado. Primero debes iniciar sesión.'
            ], 401);
        }

        if (!$usuario->activo) {
            return response()->json([
                'message' => 'Este usuario está desactivado.'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Admin tiene acceso completo
        |--------------------------------------------------------------------------
        | El administrador puede entrar a todas las secciones del sistema.
        */

        if ($rolUsuario === 'admin') {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Si no se envían roles, dejamos pasar
        |--------------------------------------------------------------------------
        | Esto evita romper rutas antiguas si por error se usa el middleware
        | sin parámetros.
        */

        if (empty($roles)) {
            return $next($request);
        }

        $rolesAutorizados = collect($roles)
            ->map(fn ($rol) => $this->normalizarRol($rol))
            ->unique()
            ->values()
            ->toArray();

        if (!in_array($rolUsuario, $rolesAutorizados, true)) {
            return response()->json([
                'message' => 'No tienes permiso para realizar esta acción.',
                'rol_actual' => $rolUsuario,
                'roles_permitidos' => $rolesAutorizados,
            ], 403);
        }

        return $next($request);
    }
}