<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Empleado;
use App\Models\UsuarioSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmpleadoController extends Controller
{
    private function normalizarTexto($valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));
        return $texto === '' ? null : $texto;
    }

    private function normalizarEmail($valor): ?string
    {
        $email = strtolower(trim((string) ($valor ?? '')));
        return $email === '' ? null : $email;
    }

    private function valorBooleano(Request $request, string $campo, bool $valorDefecto = false): bool
    {
        if (!$request->has($campo)) {
            return $valorDefecto;
        }

        $valor = filter_var($request->input($campo), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $valor === null ? $valorDefecto : $valor;
    }

    private function normalizarDatos(Request $request, bool $esCreacion = true): array
    {
        $datos = [];

        $camposTexto = [
            'nombre',
            'telefono',
            'ci',
            'email',
            'cargo',
            'especialidad',
            'direccion',
            'observaciones',
        ];

        foreach ($camposTexto as $campo) {
            if ($request->has($campo)) {
                $datos[$campo] = $this->normalizarTexto($request->input($campo));
            }
        }

        if (array_key_exists('email', $datos)) {
            $datos['email'] = $this->normalizarEmail($datos['email']);
        }

        if ($request->has('comision_porcentaje')) {
            $datos['comision_porcentaje'] = (float) ($request->input('comision_porcentaje') ?? 0);
        } elseif ($esCreacion) {
            $datos['comision_porcentaje'] = 0;
        }

        if ($request->has('salario_base')) {
            $datos['salario_base'] = (float) ($request->input('salario_base') ?? 0);
        } elseif ($esCreacion) {
            $datos['salario_base'] = 0;
        }

        if ($request->has('fecha_ingreso')) {
            $datos['fecha_ingreso'] = $request->input('fecha_ingreso') ?: null;
        }

        if ($request->has('activo')) {
            $datos['activo'] = filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($datos['activo'] === null) {
                $datos['activo'] = true;
            }
        } elseif ($esCreacion) {
            $datos['activo'] = true;
        }

        return $datos;
    }

    private function reglasValidacion(bool $esCreacion = true): array
    {
        return [
            'nombre' => [$esCreacion ? 'required' : 'sometimes', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'ci' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'especialidad' => ['nullable', 'string', 'max:150'],
            'comision_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'salario_base' => ['nullable', 'numeric', 'min:0'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'fecha_ingreso' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'crear_usuario' => ['nullable', 'boolean'],
            'usuario_login' => ['nullable', 'email', 'max:255'],
            'password_usuario' => ['nullable', 'string', 'min:6'],
            'activo_usuario' => ['nullable', 'boolean'],
        ];
    }

    private function mensajesValidacion(): array
    {
        return [
            'nombre.required' => 'El nombre del empleado es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'comision_porcentaje.numeric' => 'La comisión debe ser numérica.',
            'comision_porcentaje.min' => 'La comisión no puede ser negativa.',
            'comision_porcentaje.max' => 'La comisión no puede ser mayor a 100%.',
            'salario_base.numeric' => 'El salario base debe ser numérico.',
            'salario_base.min' => 'El salario base no puede ser negativo.',
            'fecha_ingreso.date' => 'La fecha de ingreso no es válida.',
            'password_usuario.min' => 'La contraseña del usuario debe tener mínimo 6 caracteres.',
        ];
    }

    private function formatearCuentaSistema(?UsuarioSistema $cuenta): ?array
    {
        if (!$cuenta) {
            return null;
        }

        return [
            'id' => $cuenta->id,
            'nombre' => $cuenta->nombre,
            'usuario' => $cuenta->usuario,
            'rol' => $cuenta->rol ?? 'empleado',
            'empleado_id' => $cuenta->empleado_id,
            'activo' => (bool) $cuenta->activo,
            'ultimo_acceso' => $cuenta->ultimo_acceso,
        ];
    }

    private function respuestaEmpleado(Empleado $empleado, ?UsuarioSistema $cuenta = null): array
    {
        if (!$cuenta) {
            $cuenta = UsuarioSistema::where('empleado_id', $empleado->id)->first();
        }

        $datos = $empleado->toArray();
        $datos['cuenta_sistema'] = $this->formatearCuentaSistema($cuenta);

        return $datos;
    }

    private function validarCuentaNueva(Request $request, array $datosEmpleado, ?UsuarioSistema $cuentaActual = null): ?JsonResponse
    {
        $crearUsuario = $this->valorBooleano($request, 'crear_usuario', false);

        if (!$crearUsuario && !$cuentaActual) {
            return null;
        }

        $usuarioLogin = $this->normalizarEmail(
            $request->input('usuario_login') ?: ($datosEmpleado['email'] ?? null)
        );

        $passwordUsuario = trim((string) ($request->input('password_usuario') ?? ''));

        if (!$cuentaActual && $crearUsuario && !$usuarioLogin) {
            return response()->json([
                'message' => 'Para crear el acceso del empleado debes ingresar un correo de acceso o registrar el email del empleado.',
            ], 422);
        }

        if (!$cuentaActual && $crearUsuario && $passwordUsuario === '') {
            return response()->json([
                'message' => 'Para crear el acceso del empleado debes ingresar una contraseña inicial.',
            ], 422);
        }

        if ($usuarioLogin) {
            $existe = UsuarioSistema::where('usuario', $usuarioLogin)
                ->when($cuentaActual, function ($query) use ($cuentaActual) {
                    $query->where('id', '!=', $cuentaActual->id);
                })
                ->exists();

            if ($existe) {
                return response()->json([
                    'message' => 'El correo de acceso ya está registrado en otra cuenta.',
                ], 422);
            }
        }

        return null;
    }

    private function crearCuentaEmpleado(Request $request, Empleado $empleado): UsuarioSistema
    {
        return UsuarioSistema::create([
            'nombre' => $empleado->nombre,
            'usuario' => $this->normalizarEmail($request->input('usuario_login') ?: $empleado->email),
            'password' => trim((string) $request->input('password_usuario')),
            'rol' => 'empleado',
            'empleado_id' => $empleado->id,
            'token' => null,
            'activo' => $this->valorBooleano($request, 'activo_usuario', true),
            'ultimo_acceso' => null,
        ]);
    }

    private function actualizarCuentaEmpleado(Request $request, Empleado $empleado, UsuarioSistema $cuenta): UsuarioSistema
    {
        $datosCuenta = [
            'nombre' => $empleado->nombre,
            'rol' => 'empleado',
            'empleado_id' => $empleado->id,
        ];

        if ($request->has('usuario_login')) {
            $datosCuenta['usuario'] = $this->normalizarEmail($request->input('usuario_login'));
        }

        if ($request->has('password_usuario') && trim((string) $request->input('password_usuario')) !== '') {
            $datosCuenta['password'] = trim((string) $request->input('password_usuario'));
        }

        if ($request->has('activo_usuario')) {
            $datosCuenta['activo'] = $this->valorBooleano($request, 'activo_usuario', true);
        }

        $cuenta->update($datosCuenta);
        return $cuenta->refresh();
    }

    public function index(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->get('buscar', ''));
        $estado = trim((string) $request->get('estado', 'todos'));
        $especialidad = trim((string) $request->get('especialidad', ''));

        $query = Empleado::query();

        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('telefono', 'like', "%{$buscar}%")
                    ->orWhere('ci', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%")
                    ->orWhere('cargo', 'like', "%{$buscar}%")
                    ->orWhere('especialidad', 'like', "%{$buscar}%");
            });
        }

        if ($estado === 'activo') {
            $query->where('activo', true);
        }

        if ($estado === 'inactivo') {
            $query->where('activo', false);
        }

        if ($especialidad !== '') {
            $query->where('especialidad', 'like', "%{$especialidad}%");
        }

        $empleados = $query
            ->orderBy('activo', 'desc')
            ->orderBy('nombre', 'asc')
            ->get();

        $cuentas = UsuarioSistema::whereIn('empleado_id', $empleados->pluck('id'))
            ->get()
            ->keyBy('empleado_id');

        $empleadosFormateados = $empleados->map(function ($empleado) use ($cuentas) {
            return $this->respuestaEmpleado($empleado, $cuentas->get($empleado->id));
        })->values();

        return response()->json([
            'empleados' => $empleadosFormateados,
            'resumen' => [
                'total' => Empleado::count(),
                'activos' => Empleado::where('activo', true)->count(),
                'inactivos' => Empleado::where('activo', false)->count(),
                'con_acceso' => UsuarioSistema::whereNotNull('empleado_id')->where('rol', 'empleado')->count(),
            ],
        ]);
    }

    public function activos(): JsonResponse
    {
        return response()->json([
            'empleados' => Empleado::activos()->orderBy('nombre', 'asc')->get(),
        ]);
    }

    public function comisiones(Request $request): JsonResponse
    {
        $desde = $request->get('desde') ?: now()->startOfMonth()->toDateString();
        $hasta = $request->get('hasta') ?: now()->endOfMonth()->toDateString();

        $empleados = Empleado::with([
            'citas' => function ($query) use ($desde, $hasta) {
                $query->with(['pagos' => function ($q) {
                    $q->where('estado', 'pagado');
                }])
                    ->whereBetween('fecha', [$desde, $hasta])
                    ->where('estado', 'concluida');
            }
        ])
            ->orderBy('activo', 'desc')
            ->orderBy('nombre', 'asc')
            ->get();

        $detalle = $empleados->map(function ($empleado) {
            $citas = $empleado->citas;

            $totalGenerado = 0;
            $totalPagado = 0;
            $comisionTotal = 0;

            foreach ($citas as $cita) {
                $pagado = (float) $cita->pagos->sum('monto');
                $base = $pagado > 0 ? $pagado : (($cita->estado_pago === 'pagado') ? (float) $cita->precio : 0);

                $totalGenerado += (float) $cita->precio;
                $totalPagado += $base;
                $comisionTotal += $empleado->calcularComision($base);
            }

            return [
                'empleado_id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'cargo' => $empleado->cargo,
                'especialidad' => $empleado->especialidad,
                'activo' => (bool) $empleado->activo,
                'comision_porcentaje' => (float) $empleado->comision_porcentaje,
                'cantidad_citas' => $citas->count(),
                'total_generado' => round($totalGenerado, 2),
                'total_pagado' => round($totalPagado, 2),
                'comision_total' => round($comisionTotal, 2),
            ];
        });

        return response()->json([
            'desde' => $desde,
            'hasta' => $hasta,
            'resumen' => [
                'total_empleados' => $detalle->count(),
                'total_citas' => $detalle->sum('cantidad_citas'),
                'total_generado' => round($detalle->sum('total_generado'), 2),
                'total_pagado' => round($detalle->sum('total_pagado'), 2),
                'total_comisiones' => round($detalle->sum('comision_total'), 2),
            ],
            'comisiones' => $detalle->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->reglasValidacion(true), $this->mensajesValidacion());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datos = $this->normalizarDatos($request, true);
        $errorCuenta = $this->validarCuentaNueva($request, $datos);

        if ($errorCuenta) {
            return $errorCuenta;
        }

        $resultado = DB::transaction(function () use ($request, $datos) {
            $empleado = Empleado::create($datos);
            $cuenta = null;

            if ($this->valorBooleano($request, 'crear_usuario', false)) {
                $cuenta = $this->crearCuentaEmpleado($request, $empleado);
            }

            return [
                'empleado' => $empleado->refresh(),
                'cuenta' => $cuenta,
            ];
        });

        return response()->json([
            'message' => $resultado['cuenta']
                ? 'Empleado registrado correctamente con acceso al sistema.'
                : 'Empleado registrado correctamente.',
            'empleado' => $this->respuestaEmpleado($resultado['empleado'], $resultado['cuenta']),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $empleado = Empleado::findOrFail($id);

        $validator = Validator::make($request->all(), $this->reglasValidacion(false), $this->mensajesValidacion());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datos = $this->normalizarDatos($request, false);
        $cuentaActual = UsuarioSistema::where('empleado_id', $empleado->id)->first();

        $errorCuenta = $this->validarCuentaNueva($request, array_merge($empleado->toArray(), $datos), $cuentaActual);

        if ($errorCuenta) {
            return $errorCuenta;
        }

        $resultado = DB::transaction(function () use ($request, $empleado, $datos, $cuentaActual) {
            if (!empty($datos)) {
                $empleado->update($datos);
                $empleado->refresh();
            }

            $cuenta = $cuentaActual;

            if ($cuenta) {
                $cuenta = $this->actualizarCuentaEmpleado($request, $empleado, $cuenta);
            } elseif ($this->valorBooleano($request, 'crear_usuario', false)) {
                $cuenta = $this->crearCuentaEmpleado($request, $empleado);
            }

            return [
                'empleado' => $empleado->refresh(),
                'cuenta' => $cuenta,
            ];
        });

        return response()->json([
            'message' => 'Empleado actualizado correctamente.',
            'empleado' => $this->respuestaEmpleado($resultado['empleado'], $resultado['cuenta']),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $empleado = Empleado::findOrFail($id);

        DB::transaction(function () use ($empleado) {
            UsuarioSistema::where('empleado_id', $empleado->id)
                ->update([
                    'activo' => false,
                    'token' => null,
                ]);

            $empleado->delete();
        });

        return response()->json([
            'message' => 'Empleado eliminado correctamente. Su acceso al sistema fue desactivado.',
        ]);
    }
}