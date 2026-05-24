<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    public function index()
    {
        return response()->json(
            Cliente::orderBy('created_at', 'desc')->get()
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente = Cliente::create([
            'nombre' => trim($request->nombre),
            'telefono' => $request->telefono,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Cliente registrado correctamente',
            'cliente' => $cliente
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente->update([
            'nombre' => trim($request->nombre),
            'telefono' => $request->telefono,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Cliente actualizado correctamente',
            'cliente' => $cliente
        ]);
    }

    public function destroy($id)
    {
        Cliente::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente'
        ]);
    }

    // =====================================================
    // 📜 HISTORIAL DEL CLIENTE
    // Buscar por nombre o teléfono
    // URL: /api/clientes/historial/buscar?buscar=juan
    // =====================================================
    public function historial(Request $request)
    {
        $buscar = trim($request->get('buscar', ''));

        if ($buscar === '') {
            return response()->json([
                'message' => 'Debe ingresar un nombre o teléfono para buscar.',
                'clientes' => []
            ], 422);
        }

        $clientes = Cliente::with([
                'citas' => function ($query) {
                    $query->with('pagos')
                        ->orderBy('fecha', 'desc')
                        ->orderBy('hora', 'desc');
                },
                'pagos' => function ($query) {
                    $query->with('cita')
                        ->orderBy('fecha_pago', 'desc');
                }
            ])
            ->where(function ($query) use ($buscar) {
                $query->where('nombre', 'LIKE', "%{$buscar}%")
                    ->orWhere('telefono', 'LIKE', "%{$buscar}%");
            })
            ->orderBy('nombre', 'asc')
            ->get();

        if ($clientes->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron clientes con ese nombre o teléfono.',
                'clientes' => []
            ], 404);
        }

        $resultado = $clientes->map(function ($cliente) {
            return [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'telefono' => $cliente->telefono,
                'email' => $cliente->email,

                'total_citas' => $cliente->citas->count(),
                'citas_pendientes' => $cliente->citas->where('estado', 'pendiente')->count(),
                'citas_concluidas' => $cliente->citas->where('estado', 'concluida')->count(),

                'total_pagado' => $cliente->pagos->sum('monto'),

                'citas' => $cliente->citas,
                'pagos' => $cliente->pagos,
            ];
        });

        return response()->json([
            'clientes' => $resultado
        ]);
    }
}