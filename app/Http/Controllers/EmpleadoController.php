<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpleadoController extends Controller
{
    private function normalizarTexto($valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
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
            $datos['activo'] = filter_var(
                $request->input('activo'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

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
        ];
    }

    private function mensajesValidacion(): array
    {
        return [
            'nombre.required' => 'El nombre del empleado es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto.',
            'nombre.max' => 'El nombre es demasiado largo.',

            'telefono.max' => 'El teléfono es demasiado largo.',
            'ci.max' => 'El CI es demasiado largo.',

            'email.email' => 'El correo electrónico no es válido.',
            'email.max' => 'El correo electrónico es demasiado largo.',

            'cargo.max' => 'El cargo es demasiado largo.',
            'especialidad.max' => 'La especialidad es demasiado larga.',

            'comision_porcentaje.numeric' => 'La comisión debe ser numérica.',
            'comision_porcentaje.min' => 'La comisión no puede ser negativa.',
            'comision_porcentaje.max' => 'La comisión no puede ser mayor a 100%.',

            'salario_base.numeric' => 'El salario base debe ser numérico.',
            'salario_base.min' => 'El salario base no puede ser negativo.',

            'direccion.max' => 'La dirección es demasiado larga.',
            'fecha_ingreso.date' => 'La fecha de ingreso no es válida.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
            'observaciones.max' => 'Las observaciones son demasiado largas.',
        ];
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

        $total = Empleado::count();
        $activos = Empleado::where('activo', true)->count();
        $inactivos = Empleado::where('activo', false)->count();

        return response()->json([
            'empleados' => $empleados,
            'resumen' => [
                'total' => $total,
                'activos' => $activos,
                'inactivos' => $inactivos,
            ],
        ]);
    }

    public function activos(): JsonResponse
    {
        $empleados = Empleado::activos()
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json([
            'empleados' => $empleados,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            $this->reglasValidacion(true),
            $this->mensajesValidacion()
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datos = $this->normalizarDatos($request, true);

        $empleado = Empleado::create($datos);

        return response()->json([
            'message' => 'Empleado registrado correctamente.',
            'empleado' => $empleado,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $empleado = Empleado::findOrFail($id);

        $validator = Validator::make(
            $request->all(),
            $this->reglasValidacion(false),
            $this->mensajesValidacion()
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datos = $this->normalizarDatos($request, false);

        if (empty($datos)) {
            return response()->json([
                'message' => 'No se enviaron datos para actualizar.',
                'empleado' => $empleado,
            ]);
        }

        $empleado->update($datos);
        $empleado->refresh();

        return response()->json([
            'message' => 'Empleado actualizado correctamente.',
            'empleado' => $empleado,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $empleado = Empleado::findOrFail($id);

        $empleado->delete();

        return response()->json([
            'message' => 'Empleado eliminado correctamente.',
        ]);
    }
}