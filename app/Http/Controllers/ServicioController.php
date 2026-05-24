<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServicioController extends Controller
{
    public function index()
    {
        return response()->json(
            Servicio::orderBy('grupo', 'asc')
                ->orderBy('nombre', 'asc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grupo' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ], [
            'grupo.required' => 'El servicio principal es obligatorio.',
            'nombre.required' => 'El nombre del combo es obligatorio.',
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
            'grupo' => trim($request->grupo),
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
            'grupo' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $servicio->update([
            'grupo' => trim($request->grupo),
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