<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServicioController extends Controller
{
    private function serviciosBase()
    {
        return [
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'CLEAN BROWS',
                'descripcion' => 'Depilación + Visagismo',
                'precio' => 25,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'BROWS PRO',
                'descripcion' => 'Henna + Depilación y Visagismo',
                'precio' => 80,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LAMI BROWS',
                'descripcion' => 'Laminado + Vitaminas + Depilación y Visagismo',
                'precio' => 80,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LASH PERFECT',
                'descripcion' => 'Lifting + Tinte efecto rimel',
                'precio' => 85,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'PERFECT BROWS',
                'descripcion' => 'Laminado + Henna + Depilación + Visagismo',
                'precio' => 135,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'GLOW UP EXPRESS',
                'descripcion' => 'Laminado + Henna + Depilación y Visagismo + Lifting + Tinte efecto rimel',
                'precio' => 220,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'PERFECT EXPRESS',
                'descripcion' => 'Henna + Depilación y Visagismo + Lifting + Tinte efecto rimel',
                'precio' => 165,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'LASH & BROWS EXPRESS',
                'descripcion' => 'Laminado + Lifting + Tinte efecto rimel + Vitaminas + Depilación y Visagismo',
                'precio' => 165,
                'activo' => true,
            ],
            [
                'categoria' => 'CEJAS Y PESTAÑAS',
                'nombre' => 'RETOQUE BROWS PRO',
                'descripcion' => 'Henna',
                'precio' => 40,
                'activo' => true,
            ],
        ];
    }

    private function crearServiciosInicialesSiNoExisten()
    {
        if (Servicio::count() > 0) {
            return;
        }

        foreach ($this->serviciosBase() as $servicio) {
            Servicio::create($servicio);
        }
    }

    public function index()
    {
        $this->crearServiciosInicialesSiNoExisten();

        $servicios = Servicio::orderBy('categoria', 'asc')
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json([
            'servicios' => $servicios
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categoria' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ], [
            'categoria.required' => 'La categoría es obligatoria.',
            'nombre.required' => 'El nombre del servicio es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser numérico.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $servicio = Servicio::create([
            'categoria' => trim($request->categoria),
            'nombre' => trim($request->nombre),
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
            'activo' => $request->activo ?? true,
        ]);

        return response()->json([
            'message' => 'Servicio registrado correctamente.',
            'servicio' => $servicio
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $servicio = Servicio::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'categoria' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ], [
            'categoria.required' => 'La categoría es obligatoria.',
            'nombre.required' => 'El nombre del servicio es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser numérico.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $servicio->update([
            'categoria' => trim($request->categoria),
            'nombre' => trim($request->nombre),
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
            'activo' => $request->activo ?? true,
        ]);

        return response()->json([
            'message' => 'Servicio actualizado correctamente.',
            'servicio' => $servicio
        ]);
    }

    public function destroy($id)
    {
        $servicio = Servicio::findOrFail($id);

        $servicio->update([
            'activo' => false
        ]);

        return response()->json([
            'message' => 'Servicio desactivado correctamente.'
        ]);
    }
}