<?php

namespace App\Http\Controllers;

use App\Models\UsuarioSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private array $rolesPermitidos = [
        'admin',
        'empleado',
    ];

    private function obtenerToken(Request $request)
    {
        return $request->bearerToken()
            ?: $request->header('X-Auth-Token')
            ?: $request->input('token');
    }

    private function normalizarRol($rol): string
    {
        $rol = strtolower(trim((string) $rol));

        return in_array($rol, $this->rolesPermitidos, true)
            ? $rol
            : 'admin';
    }

    private function obtenerUsuarioPorToken(Request $request)
    {
        $token = $this->obtenerToken($request);

        if (!$token) {
            return null;
        }

        return UsuarioSistema::where('token', $token)->first();
    }

    private function respuestaUsuario(UsuarioSistema $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'usuario' => $usuario->usuario,
            'rol' => $this->normalizarRol($usuario->rol ?? 'admin'),
            'empleado_id' => $usuario->empleado_id ?? null,
            'foto_perfil' => $usuario->foto_perfil ?? null,
            'activo' => (bool) $usuario->activo,
            'ultimo_acceso' => $usuario->ultimo_acceso,
        ];
    }

    private function validarFotoPerfil(?string $foto): bool
    {
        if (!$foto) {
            return false;
        }

        return preg_match('/^data:image\/(png|jpg|jpeg|webp);base64,/', $foto) === 1;
    }

    // =====================================================
    // 🔐 REGISTRO SEGURO DE ADMINISTRADOR
    // =====================================================
    public function register(Request $request)
    {
        $codigoSistema = env('ADMIN_REGISTER_CODE');

        if (!$codigoSistema) {
            return response()->json([
                'message' => 'El registro está desactivado. Falta configurar el código de seguridad del sistema.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'usuario' => 'required|email|max:255|unique:usuario_sistemas,usuario',
            'password' => 'required|string|min:6',
            'codigo_registro' => 'required|string',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'usuario.required' => 'El correo es obligatorio.',
            'usuario.email' => 'Debe ingresar un correo válido.',
            'usuario.unique' => 'Este correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener mínimo 6 caracteres.',
            'codigo_registro.required' => 'El código de seguridad es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!hash_equals($codigoSistema, $request->codigo_registro)) {
            return response()->json([
                'message' => 'Código de seguridad incorrecto. No puedes crear una cuenta de administrador.'
            ], 403);
        }

        $token = Str::random(80);

        $usuario = UsuarioSistema::create([
            'nombre' => trim($request->nombre),
            'usuario' => strtolower(trim($request->usuario)),
            'password' => $request->password,
            'rol' => 'admin',
            'empleado_id' => null,
            'foto_perfil' => null,
            'token' => $token,
            'activo' => true,
            'ultimo_acceso' => now(),
        ]);

        return response()->json([
            'message' => 'Administrador registrado correctamente.',
            'token' => $token,
            'usuario' => $this->respuestaUsuario($usuario),
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

        $usuario = $usuario->fresh();

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token' => $token,
            'usuario' => $this->respuestaUsuario($usuario),
        ]);
    }

    // =====================================================
    // 👤 USUARIO ACTUAL
    // =====================================================
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

        if (!$usuario->activo) {
            return response()->json([
                'message' => 'Este usuario está desactivado.'
            ], 403);
        }

        return response()->json([
            'usuario' => $this->respuestaUsuario($usuario),
        ]);
    }

    // =====================================================
    // 🖼️ ACTUALIZAR FOTO DE PERFIL
    // =====================================================
    public function actualizarFotoPerfil(Request $request)
    {
        $usuario = $this->obtenerUsuarioPorToken($request);

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

        $validator = Validator::make($request->all(), [
            'foto_perfil' => 'required|string|max:3000000',
        ], [
            'foto_perfil.required' => 'La foto de perfil es obligatoria.',
            'foto_perfil.string' => 'La foto debe enviarse en formato válido.',
            'foto_perfil.max' => 'La foto es demasiado grande. Usa una imagen más pequeña.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $fotoPerfil = $request->input('foto_perfil');

        if (!$this->validarFotoPerfil($fotoPerfil)) {
            return response()->json([
                'message' => 'Formato de imagen inválido. Usa JPG, PNG o WEBP.'
            ], 422);
        }

        $usuario->update([
            'foto_perfil' => $fotoPerfil,
        ]);

        $usuario = $usuario->fresh();

        return response()->json([
            'message' => 'Foto de perfil actualizada correctamente.',
            'usuario' => $this->respuestaUsuario($usuario),
        ]);
    }

    // =====================================================
    // 🗑️ ELIMINAR FOTO DE PERFIL
    // =====================================================
    public function eliminarFotoPerfil(Request $request)
    {
        $usuario = $this->obtenerUsuarioPorToken($request);

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

        $usuario->update([
            'foto_perfil' => null,
        ]);

        $usuario = $usuario->fresh();

        return response()->json([
            'message' => 'Foto de perfil eliminada correctamente.',
            'usuario' => $this->respuestaUsuario($usuario),
        ]);
    }

    // =====================================================
    // 🚪 CERRAR SESIÓN
    // =====================================================
    public function logout(Request $request)
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

        $usuario->update([
            'token' => null
        ]);

        return response()->json([
            'message' => 'Sesión cerrada correctamente.'
        ]);
    }
}