<?php

namespace App\Http\Controllers;

use App\Models\UsuarioSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function obtenerToken(Request $request)
    {
        return $request->bearerToken()
            ?: $request->header('X-Auth-Token')
            ?: $request->input('token');
    }

    private function usuarioDesdeRequest(Request $request)
    {
        $usuario = $request->attributes->get('usuario_sistema');

        if ($usuario) {
            return $usuario;
        }

        $token = $this->obtenerToken($request);

        if (!$token) {
            return null;
        }

        return UsuarioSistema::where('token', $token)->first();
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usuario' => 'required|string',
            'password' => 'required|string',
        ], [
            'usuario.required' => 'El usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario = UsuarioSistema::where('usuario', $request->usuario)->first();

        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'message' => 'Usuario o contraseña incorrectos.'
            ], 401);
        }

        if (!$usuario->activo) {
            return response()->json([
                'message' => 'Este usuario está desactivado.'
            ], 403);
        }

        $token = Str::random(80);

        $usuario->update([
            'token' => $token,
            'ultimo_acceso' => now(),
        ]);

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'usuario' => $usuario->usuario,
                'activo' => $usuario->activo,
                'ultimo_acceso' => $usuario->ultimo_acceso,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $usuario = $this->usuarioDesdeRequest($request);

        if (!$usuario) {
            return response()->json([
                'message' => 'Sesión inválida o expirada.'
            ], 401);
        }

        return response()->json([
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'usuario' => $usuario->usuario,
                'activo' => $usuario->activo,
                'ultimo_acceso' => $usuario->ultimo_acceso,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $usuario = $this->usuarioDesdeRequest($request);

        if (!$usuario) {
            return response()->json([
                'message' => 'Sesión inválida o expirada.'
            ], 401);
        }

        $usuario->update([
            'token' => null
        ]);

        return response()->json([
            'message' => 'Sesión cerrada correctamente.'
        ]);
    }
}