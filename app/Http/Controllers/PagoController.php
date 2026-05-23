<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PagoController extends Controller
{
    public function index()
    {
        return response()->json(
            Pago::with('cita.cliente')->orderBy('fecha_pago', 'desc')->get()
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cita_id' => 'required|exists:citas,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|in:efectivo,qr,transferencia',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            $cita = Cita::findOrFail($request->cita_id);

            if ($cita->estado_pago === 'pagado') {
                return response()->json(['message' => 'Esta cita ya fue pagada.'], 422);
            }

            $pago = Pago::create([
                'cita_id' => $cita->id,
                'monto' => $request->monto,
                'metodo' => $request->metodo,
                'estado' => 'pagado',
                'fecha_pago' => Carbon::now(),
            ]);

            $cita->update([
                'estado_pago' => 'pagado',
                'metodo_pago' => $request->metodo,
            ]);

            return response()->json(['message' => 'Pago registrado correctamente', 'pago' => $pago->load('cita.cliente')], 201);
        });
    }

    public function historial($cita_id)
    {
        return response()->json(
            Pago::with('cita.cliente')->where('cita_id', $cita_id)->orderBy('fecha_pago', 'desc')->get()
        );
    }

    public function factura($id)
    {
        $pago = Pago::with('cita.cliente')->findOrFail($id);

        return response()->json([
            'id' => $pago->id,
            'cliente' => $pago->cita?->cliente?->nombre ?? 'Sin cliente',
            'servicio' => $pago->cita?->servicio,
            'fecha_cita' => $pago->cita?->fecha,
            'hora_cita' => $pago->cita?->hora,
            'monto' => $pago->monto,
            'metodo' => $pago->metodo,
            'estado' => $pago->estado,
            'fecha_pago' => $pago->fecha_pago,
        ]);
    }

    public function destroy($id)
    {
        $pago = Pago::findOrFail($id);
        $cita = $pago->cita;
        $pago->delete();

        if ($cita && $cita->pagos()->count() === 0) {
            $cita->update(['estado_pago' => 'pendiente', 'metodo_pago' => null]);
        }

        return response()->json(['message' => 'Pago eliminado correctamente']);
    }
}
