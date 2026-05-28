<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Pago;
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
            // Si la tabla todavía no existe o Render está iniciando,
            // usamos valores base para no romper los reportes.
        }

        return $this->valoresBaseConfiguracion();
    }

    private function dinero($monto, string $moneda = 'Bs'): string
    {
        return trim($moneda . ' ' . number_format((float) $monto, 2, '.', ','));
    }

    private function categoriaMetodo(?string $metodo): string
    {
        $texto = Str::of($metodo ?? '')
            ->lower()
            ->ascii()
            ->toString();

        if (Str::contains($texto, ['efectivo', 'cash'])) {
            return 'efectivo';
        }

        if (Str::contains($texto, ['qr', 'q.r'])) {
            return 'qr';
        }

        if (Str::contains($texto, ['transferencia', 'transf', 'banco', 'deposito'])) {
            return 'transferencia';
        }

        if (Str::contains($texto, ['tarjeta', 'debito', 'credito', 'card'])) {
            return 'tarjeta';
        }

        return 'otros';
    }

    private function resumenMetodosBase(string $moneda): array
    {
        return [
            'efectivo' => [
                'label' => 'Efectivo',
                'cantidad' => 0,
                'total' => 0,
                'total_texto' => $this->dinero(0, $moneda),
            ],
            'qr' => [
                'label' => 'QR',
                'cantidad' => 0,
                'total' => 0,
                'total_texto' => $this->dinero(0, $moneda),
            ],
            'transferencia' => [
                'label' => 'Transferencia',
                'cantidad' => 0,
                'total' => 0,
                'total_texto' => $this->dinero(0, $moneda),
            ],
            'tarjeta' => [
                'label' => 'Tarjeta',
                'cantidad' => 0,
                'total' => 0,
                'total_texto' => $this->dinero(0, $moneda),
            ],
            'otros' => [
                'label' => 'Otros',
                'cantidad' => 0,
                'total' => 0,
                'total_texto' => $this->dinero(0, $moneda),
            ],
        ];
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

    public function cajaDiaria(Request $request)
    {
        Carbon::setLocale('es');

        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?: 'Bs';

        $fecha = $request->get('fecha')
            ? Carbon::parse($request->get('fecha'))->toDateString()
            : now()->toDateString();

        $fechaCarbon = Carbon::parse($fecha);

        $pagos = Pago::with('cita.cliente')
            ->whereDate('fecha_pago', $fecha)
            ->orderBy('fecha_pago', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $citas = Cita::with(['cliente', 'pagos'])
            ->whereDate('fecha', $fecha)
            ->orderBy('hora', 'asc')
            ->get();

        $totalCobrado = (float) $pagos->sum(function ($pago) {
            return (float) ($pago->monto ?? 0);
        });

        $resumenMetodos = $this->resumenMetodosBase($moneda);

        foreach ($pagos as $pago) {
            $categoria = $this->categoriaMetodo($pago->metodo);

            $resumenMetodos[$categoria]['cantidad']++;
            $resumenMetodos[$categoria]['total'] += (float) ($pago->monto ?? 0);
        }

        foreach ($resumenMetodos as $clave => $datosMetodo) {
            $resumenMetodos[$clave]['total'] = round((float) $datosMetodo['total'], 2);
            $resumenMetodos[$clave]['total_texto'] = $this->dinero($datosMetodo['total'], $moneda);
        }

        $citasPagadas = $citas->filter(function ($cita) {
            $precio = (float) ($cita->precio ?? 0);
            $totalPagado = (float) $cita->pagos->sum('monto');

            return $totalPagado > 0 && ($precio <= 0 || $totalPagado >= $precio || $cita->estado_pago === 'pagado');
        })->count();

        $citasPendientesPago = $citas->filter(function ($cita) {
            $precio = (float) ($cita->precio ?? 0);
            $totalPagado = (float) $cita->pagos->sum('monto');

            if ($cita->estado === 'cancelada') {
                return false;
            }

            if ($precio <= 0) {
                return $totalPagado <= 0;
            }

            return $totalPagado < $precio;
        })->count();

        $ticketPromedio = $pagos->count() > 0
            ? $totalCobrado / $pagos->count()
            : 0;

        $pagosDetalle = $pagos->map(function ($pago) use ($moneda) {
            $cliente = $pago->cita?->cliente;
            $cita = $pago->cita;

            return [
                'id' => $pago->id,
                'fecha_pago' => $pago->fecha_pago,
                'hora_pago' => $pago->fecha_pago ? Carbon::parse($pago->fecha_pago)->format('H:i') : '',
                'monto' => (float) ($pago->monto ?? 0),
                'monto_texto' => $this->dinero($pago->monto, $moneda),
                'metodo' => $pago->metodo,
                'estado' => $pago->estado,
                'cliente' => $cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cliente?->telefono ?? '',
                'servicio' => $cita?->servicio ?? 'Sin servicio',
                'fecha_cita' => $cita?->fecha ?? '',
                'hora_cita' => $cita?->hora ?? '',
            ];
        })->values();

        $citasDetalle = $citas->map(function ($cita) use ($moneda) {
            $precio = (float) ($cita->precio ?? 0);
            $totalPagado = (float) $cita->pagos->sum('monto');
            $pendiente = max($precio - $totalPagado, 0);

            return [
                'id' => $cita->id,
                'fecha' => $cita->fecha,
                'hora' => $cita->hora,
                'cliente' => $cita->cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cita->cliente?->telefono ?? '',
                'servicio' => $cita->servicio ?? 'Sin servicio',
                'estado' => $cita->estado ?? 'pendiente',
                'estado_pago' => $cita->estado_pago ?? 'pendiente',
                'precio' => $precio,
                'precio_texto' => $this->dinero($precio, $moneda),
                'total_pagado' => $totalPagado,
                'total_pagado_texto' => $this->dinero($totalPagado, $moneda),
                'pendiente' => $pendiente,
                'pendiente_texto' => $this->dinero($pendiente, $moneda),
            ];
        })->values();

        $datos = [
            'titulo' => 'Caja diaria ' . ($configuracion['nombre_corto'] ?: 'AUREA Beauty'),
            'configuracion' => $configuracion,

            'nombre_negocio' => $configuracion['nombre_negocio'] ?: 'AUREA Beauty Salon',
            'nombre_corto' => $configuracion['nombre_corto'] ?: 'AUREA Beauty',
            'slogan' => $configuracion['slogan'] ?: 'Sistema inteligente para salones de belleza',
            'telefono' => $configuracion['telefono'] ?? '',
            'whatsapp' => $configuracion['whatsapp'] ?? '',
            'direccion' => $configuracion['direccion'] ?? '',
            'logo_url' => $configuracion['logo_url'] ?? '',
            'moneda' => $moneda,

            'fecha' => $fecha,
            'fecha_texto' => ucfirst($fechaCarbon->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
            'fecha_generado' => now()->format('d/m/Y H:i'),

            'total_cobrado' => round($totalCobrado, 2),
            'total_cobrado_texto' => $this->dinero($totalCobrado, $moneda),
            'cantidad_pagos' => $pagos->count(),
            'ticket_promedio' => round($ticketPromedio, 2),
            'ticket_promedio_texto' => $this->dinero($ticketPromedio, $moneda),

            'citas_dia' => $citas->count(),
            'citas_pagadas' => $citasPagadas,
            'citas_pendientes_pago' => $citasPendientesPago,
            'citas_pendientes' => $citas->where('estado', 'pendiente')->count(),
            'citas_concluidas' => $citas->where('estado', 'concluida')->count(),
            'citas_canceladas' => $citas->where('estado', 'cancelada')->count(),

            'metodos' => $resumenMetodos,
            'pagos' => $pagosDetalle,
            'citas' => $citasDetalle,
        ];

        $pdf = Pdf::loadView('pdf.caja-diaria', $datos)
            ->setPaper('a4', 'portrait');

        $nombreSistema = Str::slug($configuracion['nombre_corto'] ?: 'aurea_beauty');
        $nombreArchivo = 'caja_diaria_' . $nombreSistema . '_' . $fecha . '.pdf';

        return $pdf->download($nombreArchivo);
    }
}