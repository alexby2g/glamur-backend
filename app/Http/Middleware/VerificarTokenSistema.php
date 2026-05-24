<?php

namespace App\Http\Middleware;

use App\Models\UsuarioSistema;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarTokenSistema
{
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

        $usuario = UsuarioSistema::where('token', $token)->first();

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

        $request->attributes->set('usuario_sistema', $usuario);

        return $next($request);
    }
}