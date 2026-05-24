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

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'usuario' => 'required|email|max:255|unique:usuario_sistemas,usuario',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'nombre.required' => 'El nombre completo es obligatorio.',
            'usuario.required' => 'El correo Gmail es obligatorio.',
            'usuario.email' => 'Debe ingresar un correo válido.',
            'usuario.unique' => 'Este correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener mínimo 6 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $token = Str::random(80);

        $usuario = UsuarioSistema::create([
            'nombre' => trim($request->nombre),
            'usuario' => trim($request->usuario),
            'password' => $request->password,
            'token' => $token,
            'activo' => true,
            'ultimo_acceso' => now(),
        ]);

        return response()->json([
            'message' => 'Cuenta creada correctamente.',
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'usuario' => $usuario->usuario,
                'activo' => $usuario->activo,
                'ultimo_acceso' => $usuario->ultimo_acceso,
            ]
        ], 201);
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

        $usuario = UsuarioSistema::where('usuario', trim($request->usuario))->first();

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
        $token = $this->obtenerToken($request);

        if (!$token) {
            return response()->json([
                'message' => 'Token no enviado.'
            ], 401);
        }

        $usuario = UsuarioSistema::where('token', $token)->first();

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
        $token = $this->obtenerToken($request);

        if (!$token) {
            return response()->json([
                'message' => 'Token no enviado.'
            ], 401);
        }

        $usuario = UsuarioSistema::where('token', $token)->first();

        if ($usuario) {
            $usuario->update([
                'token' => null
            ]);
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.'
        ]);
    }
}