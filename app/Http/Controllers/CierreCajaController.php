<?php

namespace App\Http\Controllers;

use App\Models\CierreCaja;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CierreCajaController extends Controller
{
    private function fecha(Request $request): string
    {
        return Carbon::parse($request->input('fecha', now('America/La_Paz')->toDateString()), 'America/La_Paz')->toDateString();
    }

    private function totales(string $fecha): array
    {
        $pagos = Pago::query()->whereDate('fecha_pago', $fecha)->get();
        $efectivo = (float) $pagos->sum('monto_efectivo');
        $qr = (float) $pagos->sum('monto_qr');
        $transferencia = (float) $pagos->sum('monto_transferencia');
        $total = (float) $pagos->sum('monto');

        return [
            'total_efectivo' => round($efectivo, 2),
            'total_qr' => round($qr, 2),
            'total_transferencia' => round($transferencia, 2),
            'total_otros' => round(max($total - $efectivo - $qr - $transferencia, 0), 2),
            'total_cobrado' => round($total, 2),
        ];
    }

    private function respuesta(CierreCaja $caja): CierreCaja
    {
        return $caja->load(['usuarioApertura:id,nombre,usuario', 'usuarioCierre:id,nombre,usuario']);
    }

    public function mostrar(Request $request)
    {
        $fecha = $this->fecha($request);
        $caja = CierreCaja::with(['usuarioApertura:id,nombre,usuario', 'usuarioCierre:id,nombre,usuario'])
            ->whereDate('fecha', $fecha)->first();

        return response()->json([
            'fecha' => $fecha,
            'caja' => $caja,
            'totales_actuales' => $this->totales($fecha),
        ]);
    }

    public function abrir(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'nullable|date',
            'fondo_inicial' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $fecha = $this->fecha($request);
        if (CierreCaja::whereDate('fecha', $fecha)->exists()) {
            return response()->json(['message' => 'La caja de esta fecha ya fue abierta.'], 409);
        }

        $usuario = $request->attributes->get('usuario_sistema');
        $caja = CierreCaja::create([
            'fecha' => $fecha,
            'fondo_inicial' => round((float) $request->fondo_inicial, 2),
            'observacion' => $request->observacion,
            'abierto_por' => $usuario?->id,
            'abierta_at' => now('America/La_Paz'),
        ]);

        return response()->json(['message' => 'Caja abierta correctamente.', 'caja' => $this->respuesta($caja)], 201);
    }

    public function cerrar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'nullable|date',
            'efectivo_contado' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $fecha = $this->fecha($request);
        $usuario = $request->attributes->get('usuario_sistema');

        $caja = DB::transaction(function () use ($fecha, $request, $usuario) {
            $caja = CierreCaja::whereDate('fecha', $fecha)->lockForUpdate()->first();
            if (!$caja) abort(409, 'Primero debes abrir la caja de esta fecha.');
            if ($caja->estado === 'cerrada') abort(409, 'La caja de esta fecha ya está cerrada.');

            $totales = $this->totales($fecha);
            $esperado = round((float) $caja->fondo_inicial + $totales['total_efectivo'], 2);
            $contado = round((float) $request->efectivo_contado, 2);

            $caja->update(array_merge($totales, [
                'estado' => 'cerrada',
                'efectivo_esperado' => $esperado,
                'efectivo_contado' => $contado,
                'diferencia' => round($contado - $esperado, 2),
                'observacion' => $request->observacion ?? $caja->observacion,
                'cerrado_por' => $usuario?->id,
                'cerrada_at' => now('America/La_Paz'),
            ]));

            return $caja;
        });

        return response()->json(['message' => 'Caja cerrada correctamente.', 'caja' => $this->respuesta($caja)]);
    }

    public function historial(Request $request)
    {
        $query = CierreCaja::with(['usuarioApertura:id,nombre,usuario', 'usuarioCierre:id,nombre,usuario'])
            ->orderByDesc('fecha');

        if ($request->filled('desde')) $query->whereDate('fecha', '>=', $request->desde);
        if ($request->filled('hasta')) $query->whereDate('fecha', '<=', $request->hasta);

        return response()->json($query->paginate(min(max((int) $request->input('per_page', 15), 1), 50)));
    }
}
