<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Schema\Blueprint;

class ServicioController extends Controller
{
    private function asegurarTablaServicios()
    {
        if (!Schema::hasTable('servicios')) {
            Schema::create('servicios', function (Blueprint $table) {
                $table->id();
                $table->string('grupo')->default('CEJAS Y PESTAÑAS');
                $table->string('categoria')->default('CEJAS Y PESTAÑAS');
                $table->string('nombre');
                $table->text('descripcion')->nullable();
                $table->decimal('precio', 10, 2)->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('servicios')) {
            if (!Schema::hasColumn('servicios', 'grupo')) {
                Schema::table('servicios', function (Blueprint $table) {
                    $table->string('grupo')->default('CEJAS Y PESTAÑAS')->after('id');
                });
            }

            if (!Schema::hasColumn('servicios', 'categoria')) {
                Schema::table('servicios', function (Blueprint $table) {
                    $table->string('categoria')->default('CEJAS Y PESTAÑAS')->after('grupo');
                });
            }

            if (!Schema::hasColumn('servicios', 'activo')) {
                Schema::table('servicios', function (Blueprint $table) {
                    $table->boolean('activo')->default(true)->after('precio');
                });
            }
        }
    }

    private function serviciosBase()
    {
        return [
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'CLEAN BROWS',
                'descripcion' => 'Depilación + Visagismo',
                'precio' => 25,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'BROWS PRO',
                'descripcion' => 'Henna + Depilación y Visagismo',
                'precio' => 80,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LAMI BROWS',
                'descripcion' => 'Laminado + Vitaminas + Depilación y Visagismo',
                'precio' => 80,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LASH PERFECT',
                'descripcion' => 'Lifting + Tinte efecto rimel',
                'precio' => 85,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'PERFECT BROWS',
                'descripcion' => 'Laminado + Henna + Depilación + Visagismo',
                'precio' => 135,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'GLOW UP EXPRESS',
                'descripcion' => 'Laminado + Henna + Depilación y Visagismo + Lifting + Tinte efecto rimel',
                'precio' => 220,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'PERFECT EXPRESS',
                'descripcion' => 'Henna + Depilación y Visagismo + Lifting + Tinte efecto rimel',
                'precio' => 165,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LASH & BROWS EXPRESS',
                'descripcion' => 'Laminado + Lifting + Tinte efecto rimel + Vitaminas + Depilación y Visagismo',
                'precio' => 165,
                'activo' => true,
            ],
            [
                'grupo' => 'CEJAS Y PESTAÑAS',
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'RETOQUE BROWS PRO',
                'descripcion' => 'Henna',
                'precio' => 40,
                'activo' => true,
            ],
        ];
    }

    private function cargarServiciosBase()
    {
        foreach ($this->serviciosBase() as $item) {
            Servicio::updateOrCreate(
                [
                    'categoria' => $item['categoria'],
                    'nombre' => $item['nombre'],
                ],
                $item
            );
        }
    }

    public function index()
    {
        $this->asegurarTablaServicios();

        if (Servicio::count() === 0) {
            $this->cargarServiciosBase();
        }

        $servicios = Servicio::orderBy('categoria', 'asc')
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json($servicios);
    }

    public function cargarBase()
    {
        $this->asegurarTablaServicios();
        $this->cargarServiciosBase();

        $servicios = Servicio::orderBy('categoria', 'asc')
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json([
            'message' => 'Combos base cargados correctamente.',
            'servicios' => $servicios,
        ]);
    }

    public function store(Request $request)
    {
        $this->asegurarTablaServicios();

        $validator = Validator::make($request->all(), [
            'grupo' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre del combo es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser numérico.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $categoria = $request->categoria ?: 'CEJAS Y PESTAÑAS';
        $grupo = $request->grupo ?: $categoria;

        $servicio = Servicio::create([
            'grupo' => trim($grupo),
            'categoria' => trim($categoria),
            'nombre' => trim($request->nombre),
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
            'activo' => $request->has('activo') ? $request->activo : true,
        ]);

        return response()->json([
            'message' => 'Servicio registrado correctamente.',
            'servicio' => $servicio,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->asegurarTablaServicios();

        $servicio = Servicio::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'grupo' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre del combo es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser numérico.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $categoria = $request->categoria ?: 'CEJAS Y PESTAÑAS';
        $grupo = $request->grupo ?: $categoria;

        $servicio->update([
            'grupo' => trim($grupo),
            'categoria' => trim($categoria),
            'nombre' => trim($request->nombre),
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
            'activo' => $request->has('activo') ? $request->activo : $servicio->activo,
        ]);

        return response()->json([
            'message' => 'Servicio actualizado correctamente.',
            'servicio' => $servicio,
        ]);
    }

    public function destroy($id)
    {
        $this->asegurarTablaServicios();

        $servicio = Servicio::findOrFail($id);
        $servicio->delete();

        return response()->json([
            'message' => 'Servicio eliminado correctamente.',
        ]);
    }
}