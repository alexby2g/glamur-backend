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

    // =====================================================
    // 📝 REGISTRO DE USUARIO DEL SISTEMA
    // =====================================================
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'usuario' => 'required|email|max:255|unique:usuario_sistemas,usuario',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'nombre.required' => 'El nombre completo es obligatorio.',
            'usuario.required' => 'El correo Gmail es obligatorio.',
            'usuario.email' => 'Debes ingresar un correo válido.',
            'usuario.unique' => 'Este usuario ya está registrado.',
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

        if (!str_ends_with(strtolower($request->usuario), '@gmail.com')) {
            return response()->json([
                'message' => 'Solo se permiten correos Gmail.'
            ], 422);
        }

        $usuario = UsuarioSistema::create([
            'nombre' => trim($request->nombre),
            'usuario' => strtolower(trim($request->usuario)),
            'password' => $request->password,
            'activo' => true,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'usuario' => $usuario->usuario,
                'activo' => $usuario->activo,
            ]
        ], 201);
    }

    // =====================================================
    // 🔐 LOGIN
    // =====================================================
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

        $usuarioInput = strtolower(trim($request->usuario));

        $usuario = UsuarioSistema::where('usuario', $usuarioInput)->first();

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

    // =====================================================
    // 👤 USUARIO ACTUAL
    // =====================================================
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

    // =====================================================
    // 🚪 CERRAR SESIÓN
    // =====================================================
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