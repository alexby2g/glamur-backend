<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Configuracion;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReporteController extends Controller
{
    private function valoresBaseConfiguracion(): array
    {
        return [
            'nombre_negocio' => 'AUREA Beauty Salon',
            'nombre_corto' => 'AUREA Beauty',
            'slogan' => 'Sistema inteligente para salones de belleza',
            'telefono' => '',
            'whatsapp' => '',
            'direccion' => '',
            'mensaje_whatsapp' => 'Hola, quiero información sobre los servicios de AUREA Beauty Salon.',
            'logo_url' => '',
            'moneda' => 'Bs',
            'activo' => true,
        ];
    }

    private function obtenerConfiguracionNegocio(): array
    {
        try {
            $configuracion = Configuracion::query()->first();

            if ($configuracion) {
                return array_merge(
                    $this->valoresBaseConfiguracion(),
                    $configuracion->toArray()
                );
            }
        } catch (\Throwable $error) {
            // Si la tabla aún no existe o Render está aplicando migraciones,
            // usamos valores base para no romper el PDF.
        }

        return $this->valoresBaseConfiguracion();
    }

    private function dinero($monto, string $moneda = 'Bs'): string
    {
        return trim($moneda . ' ' . number_format((float) $monto, 2, '.', ','));
    }

    public function extractoMensual(Request $request)
    {
        Carbon::setLocale('es');

        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?: 'Bs';

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

            $totalPendienteDia = max($totalEstimadoDia - $totalPagadoDia, 0);

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
                    'total_pendiente' => $totalPendienteDia,
                    'total_estimado_texto' => $this->dinero($totalEstimadoDia, $moneda),
                    'total_pagado_texto' => $this->dinero($totalPagadoDia, $moneda),
                    'total_pendiente_texto' => $this->dinero($totalPendienteDia, $moneda),
                ];
            }
        }

        $datos = [
            'titulo' => 'Extracto mensual ' . ($configuracion['nombre_corto'] ?: 'AUREA Beauty'),
            'configuracion' => $configuracion,

            'nombre_negocio' => $configuracion['nombre_negocio'] ?: 'AUREA Beauty Salon',
            'nombre_corto' => $configuracion['nombre_corto'] ?: 'AUREA Beauty',
            'slogan' => $configuracion['slogan'] ?: 'Sistema inteligente para salones de belleza',
            'telefono' => $configuracion['telefono'] ?? '',
            'whatsapp' => $configuracion['whatsapp'] ?? '',
            'direccion' => $configuracion['direccion'] ?? '',
            'logo_url' => $configuracion['logo_url'] ?? '',
            'moneda' => $moneda,

            'mes_nombre' => ucfirst($inicio->locale('es')->isoFormat('MMMM')),
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

            'total_estimado_texto' => $this->dinero($totalEstimado, $moneda),
            'total_pagado_texto' => $this->dinero($totalPagado, $moneda),
            'total_pendiente_texto' => $this->dinero($totalPendiente, $moneda),

            'citas_concluidas' => $citasConcluidas,
            'citas_pendientes' => $citasPendientes,
            'citas_canceladas' => $citasCanceladas,
        ];

        $pdf = Pdf::loadView('pdf.extracto-mensual', $datos)
            ->setPaper('a4', 'portrait');

        $nombreSistema = Str::slug($configuracion['nombre_corto'] ?: 'aurea_beauty');
        $nombreArchivo = 'extracto_' . $nombreSistema . '_' . $anio . '_' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.pdf';

        return $pdf->download($nombreArchivo);
    }
}