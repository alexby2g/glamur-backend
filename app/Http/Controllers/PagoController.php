<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Cita;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PagoController extends Controller
{
    public function index()
    {
        $pagos = Pago::with('cita.cliente')
            ->orderBy('fecha_pago', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($pagos);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cita_id' => 'required|exists:citas,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|string|max:50',
        ], [
            'cita_id.required' => 'La cita es obligatoria.',
            'cita_id.exists' => 'La cita seleccionada no existe.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser numérico.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'metodo.required' => 'El método de pago es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cita = Cita::with('cliente')->findOrFail($request->cita_id);

        $pago = Pago::create([
            'cita_id' => $cita->id,
            'monto' => $request->monto,
            'metodo' => $request->metodo,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ]);

        $cita->update([
            'estado_pago' => 'pagado',
            'metodo_pago' => $request->metodo,
        ]);

        $nombreCliente = $cita->cliente?->nombre ?? 'Cliente sin nombre';
        $servicio = $cita->servicio ?? 'Servicio';
        $monto = number_format((float) $pago->monto, 2);

        Notificacion::create([
            'tipo' => 'pago',
            'titulo' => 'Pago realizado',
            'mensaje' => "Se registró un pago de Bs {$monto} de {$nombreCliente}.",
            'data' => [
                'pago_id' => $pago->id,
                'cita_id' => $cita->id,
                'cliente' => $nombreCliente,
                'servicio' => $servicio,
                'monto' => (float) $pago->monto,
                'metodo' => $pago->metodo,
                'fecha_pago' => $pago->fecha_pago,
            ],
        ]);

        return response()->json([
            'message' => 'Pago registrado correctamente',
            'pago' => $pago->load('cita.cliente'),
        ], 201);
    }

    public function historial($cita_id)
    {
        $pagos = Pago::with('cita.cliente')
            ->where('cita_id', $cita_id)
            ->orderBy('fecha_pago', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($pagos);
    }

    public function factura($id)
    {
        $pago = Pago::with('cita.cliente')->findOrFail($id);

        return response()->json([
            'pago' => $pago,
        ]);
    }

    public function destroy($id)
    {
        $pago = Pago::findOrFail($id);
        $pago->delete();

        return response()->json([
            'message' => 'Pago eliminado correctamente',
        ]);
    }
}