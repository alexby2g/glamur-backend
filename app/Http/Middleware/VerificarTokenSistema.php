<?php

namespace App\Http\Middleware;

use App\Models\UsuarioSistema;
use App\Models\UsuarioSistemaToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarTokenSistema
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

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken()
            ?: $request->header('X-Auth-Token')
            ?: $request->input('token');

        if (!$token) {
            return response()->json([
                'message' => 'No autorizado. Token no enviado.'
            ], 401);
        }

        $usuario = UsuarioSistema::with('empleado')
            ->where('token', $token)
            ->first();

        $tokenMovil = null;
        if (!$usuario) {
            $tokenMovil = UsuarioSistemaToken::with('usuario.empleado')
                ->where('token_hash', hash('sha256', $token))
                ->first();

            if ($tokenMovil && $tokenMovil->expires_at && $tokenMovil->expires_at->isPast()) {
                $tokenMovil->delete();
                $tokenMovil = null;
            }

            $usuario = $tokenMovil?->usuario;
        }

        if (!$usuario) {
            return response()->json([
                'message' => 'Sesión inválida o expirada.'
            ], 401);
        }

        if (!$usuario->activo) {
            return response()->json([
                'message' => 'Este usuario está desactivado.'
            ], 403);
        }

        $rol = $this->normalizarRol($usuario->rol ?? 'empleado');

        /*
        |--------------------------------------------------------------------------
        | Datos disponibles para todos los controladores
        |--------------------------------------------------------------------------
        | Ahora cualquier controlador podrá obtener:
        |
        | $request->attributes->get('usuario_sistema')
        | $request->attributes->get('usuario_rol')
        | $request->attributes->get('usuario_empleado_id')
        | $request->attributes->get('usuario_es_admin')
        | $request->attributes->get('usuario_es_empleado')
        |
        */

        $request->attributes->set('usuario_sistema', $usuario);
        $request->attributes->set('usuario_rol', $rol);
        $request->attributes->set('usuario_empleado_id', $usuario->empleado_id);
        $request->attributes->set('usuario_es_admin', $rol === 'admin');
        $request->attributes->set('usuario_es_empleado', $rol === 'empleado');
        $request->attributes->set('usuario_token_movil', $tokenMovil);

        if ($tokenMovil) {
            $tokenMovil->forceFill(['last_used_at' => now()])->save();
        }

        return $next($request);
    }
}
