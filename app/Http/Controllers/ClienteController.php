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
            Cliente::orderBy('nombre', 'asc')->get()
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ], [
            'nombre.required' => 'El nombre completo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente = Cliente::create([
            'nombre' => $this->formatearNombre($request->nombre),
            'telefono' => $this->limpiarTexto($request->telefono),
            'email' => $this->limpiarTexto($request->email),
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
            'nombre.required' => 'El nombre completo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $cliente->update([
            'nombre' => $this->formatearNombre($request->nombre),
            'telefono' => $this->limpiarTexto($request->telefono),
            'email' => $this->limpiarTexto($request->email),
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
    // Busca por nombre o teléfono.
    // Funciona con mayúsculas y minúsculas.
    // Ejemplo:
    // /api/clientes/historial/buscar?buscar=cris
    // /api/clientes/historial/buscar?buscar=CRIS
    // /api/clientes/historial/buscar?buscar=76543210
    // =====================================================
    public function historial(Request $request)
    {
        $buscar = trim($request->get('buscar', ''));

        if ($buscar === '') {
            return response()->json([
                'message' => 'Debe ingresar un nombre o teléfono para buscar.',
                'clientes' => []
            ], 200);
        }

        $buscarMinuscula = mb_strtolower($buscar, 'UTF-8');

        $clientes = Cliente::with([
            'citas' => function ($query) {
                $query->with([
                    'pagos' => function ($pagoQuery) {
                        $pagoQuery
                            ->orderBy('fecha_pago', 'desc')
                            ->orderBy('created_at', 'desc');
                    }
                ])
                ->orderBy('fecha', 'desc')
                ->orderBy('hora', 'desc');
            }
        ])
        ->where(function ($query) use ($buscarMinuscula) {
            $query->whereRaw('LOWER(nombre) LIKE ?', ['%' . $buscarMinuscula . '%'])
                ->orWhereRaw('LOWER(COALESCE(telefono, \'\')) LIKE ?', ['%' . $buscarMinuscula . '%']);
        })
        ->orderBy('nombre', 'asc')
        ->limit(20)
        ->get();

        if ($clientes->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron clientes con ese nombre o teléfono.',
                'clientes' => []
            ], 200);
        }

        $resultado = $clientes->map(function ($cliente) {
            $pagos = collect();

            foreach ($cliente->citas as $cita) {
                foreach ($cita->pagos as $pago) {
                    $pagos->push([
                        'id' => $pago->id,
                        'cita_id' => $pago->cita_id,
                        'monto' => $pago->monto,
                        'metodo' => $pago->metodo,
                        'estado' => $pago->estado,
                        'fecha_pago' => $pago->fecha_pago,
                        'created_at' => $pago->created_at,
                        'updated_at' => $pago->updated_at,

                        'servicio' => $cita->servicio,
                        'precio_cita' => $cita->precio,
                        'fecha_cita' => $cita->fecha,
                        'hora_cita' => $cita->hora,
                        'estado_cita' => $cita->estado,
                        'estado_pago_cita' => $cita->estado_pago,
                        'metodo_pago_cita' => $cita->metodo_pago,
                    ]);
                }
            }

            $pagos = $pagos
                ->sortByDesc(function ($pago) {
                    return $pago['fecha_pago'] ?? $pago['created_at'];
                })
                ->values();

            $totalPagado = $pagos->sum(function ($pago) {
                return floatval($pago['monto'] ?? 0);
            });

            return [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'telefono' => $cliente->telefono,
                'email' => $cliente->email,

                'total_citas' => $cliente->citas->count(),
                'citas_pendientes' => $cliente->citas->where('estado', 'pendiente')->count(),
                'citas_concluidas' => $cliente->citas->where('estado', 'concluida')->count(),

                'total_pagado' => $totalPagado,

                'citas' => $cliente->citas,
                'pagos' => $pagos,
            ];
        });

        return response()->json([
            'clientes' => $resultado
        ]);
    }

    private function limpiarTexto($valor)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    private function formatearNombre($nombre)
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return $nombre;
        }

        $nombre = mb_strtolower($nombre, 'UTF-8');

        return mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
    }
}