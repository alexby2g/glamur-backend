<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function extractoMensual(Request $request)
    {
        $anio = (int) $request->get('anio', now()->year);
        $mes = (int) $request->get('mes', now()->month);

        if ($mes < 1 || $mes > 12) {
            return response()->json([
                'message' => 'Mes inválido.'
            ], 422);
        }

        if ($anio < 2000 || $anio > 2100) {
            return response()->json([
                'message' => 'Año inválido.'
            ], 422);
        }

        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin = Carbon::create($anio, $mes, 1)->endOfMonth();

        $citas = Cita::with(['cliente', 'pagos'])
            ->whereBetween('fecha', [
                $inicio->toDateString(),
                $fin->toDateString()
            ])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->get();

        $totalEstimado = $citas->sum(function ($cita) {
            return (float) ($cita->precio ?? 0);
        });

        $totalPagado = $citas->sum(function ($cita) {
            return $cita->pagos->sum(function ($pago) {
                return (float) ($pago->monto ?? 0);
            });
        });

        $totalPendiente = max($totalEstimado - $totalPagado, 0);

        $citasConcluidas = $citas->where('estado', 'concluida')->count();
        $citasPendientes = $citas->where('estado', 'pendiente')->count();
        $citasCanceladas = $citas->where('estado', 'cancelada')->count();

        $resumenDias = [];

        for ($dia = 1; $dia <= $fin->day; $dia++) {
            $fechaDia = Carbon::create($anio, $mes, $dia)->toDateString();

            $citasDia = $citas->filter(function ($cita) use ($fechaDia) {
                return Carbon::parse($cita->fecha)->toDateString() === $fechaDia;
            });

            $totalEstimadoDia = $citasDia->sum(function ($cita) {
                return (float) ($cita->precio ?? 0);
            });

            $totalPagadoDia = $citasDia->sum(function ($cita) {
                return $cita->pagos->sum(function ($pago) {
                    return (float) ($pago->monto ?? 0);
                });
            });

            if ($citasDia->count() > 0) {
                $resumenDias[] = [
                    'fecha' => $fechaDia,
                    'dia' => Carbon::parse($fechaDia)->locale('es')->isoFormat('dddd D'),
                    'total_citas' => $citasDia->count(),
                    'pendientes' => $citasDia->where('estado', 'pendiente')->count(),
                    'concluidas' => $citasDia->where('estado', 'concluida')->count(),
                    'canceladas' => $citasDia->where('estado', 'cancelada')->count(),
                    'total_estimado' => $totalEstimadoDia,
                    'total_pagado' => $totalPagadoDia,
                ];
            }
        }

        $datos = [
            'titulo' => 'Extracto mensual Glamur',
            'mes_nombre' => $inicio->locale('es')->isoFormat('MMMM'),
            'anio' => $anio,
            'fecha_inicio' => $inicio->format('d/m/Y'),
            'fecha_fin' => $fin->format('d/m/Y'),
            'fecha_generado' => now()->format('d/m/Y H:i'),
            'citas' => $citas,
            'resumenDias' => $resumenDias,
            'total_citas' => $citas->count(),
            'total_estimado' => $totalEstimado,
            'total_pagado' => $totalPagado,
            'total_pendiente' => $totalPendiente,
            'citas_concluidas' => $citasConcluidas,
            'citas_pendientes' => $citasPendientes,
            'citas_canceladas' => $citasCanceladas,
        ];

        $pdf = Pdf::loadView('pdf.extracto-mensual', $datos)
            ->setPaper('a4', 'portrait');

        $nombreArchivo = 'extracto_glamur_' . $anio . '_' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.pdf';

        return $pdf->download($nombreArchivo);
    }
}