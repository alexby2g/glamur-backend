<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Pago;
use App\Models\Empleado;
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
            // Usamos valores base para no romper los reportes.
        }

        return $this->valoresBaseConfiguracion();
    }

    private function dinero($monto, string $moneda = 'Bs'): string
    {
        return trim($moneda . ' ' . number_format((float) $monto, 2, '.', ','));
    }

    private function categoriaMetodo(?string $metodo): string
    {
        $texto = Str::of($metodo ?? '')->lower()->ascii()->toString();

        if (Str::contains($texto, ['mixto', 'combinado', 'efectivo + qr'])) return 'mixto';
        if (Str::contains($texto, ['efectivo', 'cash'])) return 'efectivo';
        if (Str::contains($texto, ['qr', 'q.r'])) return 'qr';
        if (Str::contains($texto, ['transferencia', 'transf', 'banco', 'deposito'])) return 'transferencia';
        if (Str::contains($texto, ['tarjeta', 'debito', 'credito', 'card'])) return 'tarjeta';

        return 'otros';
    }

    private function resumenMetodosBase(string $moneda): array
    {
        return [
            'efectivo' => ['label' => 'Efectivo', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'qr' => ['label' => 'QR', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'transferencia' => ['label' => 'Transferencia', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'mixto' => ['label' => 'Mixto', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'tarjeta' => ['label' => 'Tarjeta', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'otros' => ['label' => 'Otros', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
        ];
    }

    private function totalPagadoDesdeCitas($citas): float
    {
        return (float) $citas->sum(function ($cita) {
            if (($cita->estado_pago ?? null) === 'pagado') {
                return (float) ($cita->precio ?? 0);
            }

            return $cita->pagos->sum(function ($pago) {
                return (float) ($pago->monto ?? 0);
            });
        });
    }

    private function resumenEmpleado($empleado, $citas, string $moneda, bool $sinEmpleado = false): array
    {
        $totalEstimado = (float) $citas->sum(fn ($cita) => (float) ($cita->precio ?? 0));
        $totalPagado = $this->totalPagadoDesdeCitas($citas);
        $totalPendiente = max($totalEstimado - $totalPagado, 0);

        $comisionPorcentaje = $sinEmpleado ? 0 : (float) ($empleado->comision_porcentaje ?? 0);
        $comisionCalculada = ($totalPagado * $comisionPorcentaje) / 100;

        $citasDetalle = $citas->map(function ($cita) use ($moneda) {
            $precio = (float) ($cita->precio ?? 0);
            $totalPagadoCita = (($cita->estado_pago ?? null) === 'pagado')
                ? $precio
                : (float) $cita->pagos->sum('monto');
            $pendiente = max($precio - $totalPagadoCita, 0);

            return [
                'id' => $cita->id,
                'fecha' => $cita->fecha,
                'fecha_texto' => $cita->fecha ? Carbon::parse($cita->fecha)->format('d/m/Y') : '',
                'hora' => $cita->hora,
                'cliente' => $cita->cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cita->cliente?->telefono ?? '',
                'servicio' => $cita->servicio ?? 'Sin servicio',
                'estado' => $cita->estado ?? 'pendiente',
                'estado_pago' => $cita->estado_pago ?? 'pendiente',
                'precio' => $precio,
                'precio_texto' => $this->dinero($precio, $moneda),
                'total_pagado' => $totalPagadoCita,
                'total_pagado_texto' => $this->dinero($totalPagadoCita, $moneda),
                'pendiente' => $pendiente,
                'pendiente_texto' => $this->dinero($pendiente, $moneda),
            ];
        })->values();

        return [
            'empleado_id' => $sinEmpleado ? null : $empleado->id,
            'nombre' => $sinEmpleado ? 'Sin empleado asignado' : ($empleado->nombre ?? 'Empleado sin nombre'),
            'telefono' => $sinEmpleado ? '' : ($empleado->telefono ?? ''),
            'cargo' => $sinEmpleado ? '' : ($empleado->cargo ?? ''),
            'especialidad' => $sinEmpleado ? '' : ($empleado->especialidad ?? ''),
            'activo' => $sinEmpleado ? false : (bool) ($empleado->activo ?? false),
            'eliminado' => $sinEmpleado ? false : (method_exists($empleado, 'trashed') ? (bool) $empleado->trashed() : false),
            'comision_porcentaje' => round($comisionPorcentaje, 2),
            'total_citas' => $citas->count(),
            'citas_pendientes' => $citas->where('estado', 'pendiente')->count(),
            'citas_concluidas' => $citas->where('estado', 'concluida')->count(),
            'citas_canceladas' => $citas->where('estado', 'cancelada')->count(),
            'total_estimado' => round($totalEstimado, 2),
            'total_pagado' => round($totalPagado, 2),
            'total_pendiente' => round($totalPendiente, 2),
            'comision_calculada' => round($comisionCalculada, 2),
            'total_estimado_texto' => $this->dinero($totalEstimado, $moneda),
            'total_pagado_texto' => $this->dinero($totalPagado, $moneda),
            'total_pendiente_texto' => $this->dinero($totalPendiente, $moneda),
            'comision_calculada_texto' => $this->dinero($comisionCalculada, $moneda),
            'citas' => $citasDetalle,
        ];
    }

    public function empleados(Request $request)
    {
        Carbon::setLocale('es');

        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?: 'Bs';

        try {
            $desde = $request->get('desde')
                ? Carbon::parse($request->get('desde'), 'America/La_Paz')->startOfDay()
                : Carbon::now('America/La_Paz')->startOfMonth();

            $hasta = $request->get('hasta')
                ? Carbon::parse($request->get('hasta'), 'America/La_Paz')->endOfDay()
                : Carbon::now('America/La_Paz')->endOfMonth();
        } catch (\Throwable $error) {
            return response()->json(['message' => 'Las fechas enviadas no son válidas.'], 422);
        }

        if ($desde->gt($hasta)) {
            return response()->json(['message' => 'La fecha inicial no puede ser mayor que la fecha final.'], 422);
        }

        $empleadoId = $request->filled('empleado_id') ? (int) $request->get('empleado_id') : null;

        if ($empleadoId && !Empleado::withTrashed()->where('id', $empleadoId)->exists()) {
            return response()->json(['message' => 'El empleado seleccionado no existe.'], 404);
        }

        $empleados = Empleado::withTrashed()
            ->when($empleadoId, fn ($query) => $query->where('id', $empleadoId))
            ->orderBy('activo', 'desc')
            ->orderBy('nombre', 'asc')
            ->get();

        $citas = Cita::with(['cliente', 'empleado', 'pagos'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($empleadoId, fn ($query) => $query->where('empleado_id', $empleadoId))
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->get();

        $citasPorEmpleado = $citas->groupBy(fn ($cita) => $cita->empleado_id ? (string) $cita->empleado_id : 'sin_empleado');

        $reporteEmpleados = $empleados->map(function ($empleado) use ($citasPorEmpleado, $moneda) {
            $grupo = $citasPorEmpleado->get((string) $empleado->id, collect());
            return $this->resumenEmpleado($empleado, $grupo, $moneda);
        });

        if (!$empleadoId) {
            $sinEmpleado = $citasPorEmpleado->get('sin_empleado', collect());
            if ($sinEmpleado->count() > 0) {
                $reporteEmpleados->push($this->resumenEmpleado(null, $sinEmpleado, $moneda, true));
            }
        }

        $reporteEmpleados = $reporteEmpleados->sortByDesc('total_pagado')->values();

        $totalEstimadoGeneral = (float) $citas->sum(fn ($cita) => (float) ($cita->precio ?? 0));
        $totalPagadoGeneral = $this->totalPagadoDesdeCitas($citas);
        $totalPendienteGeneral = max($totalEstimadoGeneral - $totalPagadoGeneral, 0);
        $totalComisionGeneral = (float) $reporteEmpleados->sum(fn ($empleado) => (float) ($empleado['comision_calculada'] ?? 0));

        return response()->json([
            'titulo' => 'Reporte por empleados ' . ($configuracion['nombre_corto'] ?: 'AUREA Beauty'),
            'configuracion' => $configuracion,
            'filtros' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'empleado_id' => $empleadoId,
                'desde_texto' => $desde->format('d/m/Y'),
                'hasta_texto' => $hasta->format('d/m/Y'),
            ],
            'resumen' => [
                'cantidad_empleados' => $reporteEmpleados->count(),
                'total_citas' => $citas->count(),
                'citas_pendientes' => $citas->where('estado', 'pendiente')->count(),
                'citas_concluidas' => $citas->where('estado', 'concluida')->count(),
                'citas_canceladas' => $citas->where('estado', 'cancelada')->count(),
                'total_estimado' => round($totalEstimadoGeneral, 2),
                'total_pagado' => round($totalPagadoGeneral, 2),
                'total_pendiente' => round($totalPendienteGeneral, 2),
                'total_comision' => round($totalComisionGeneral, 2),
                'total_estimado_texto' => $this->dinero($totalEstimadoGeneral, $moneda),
                'total_pagado_texto' => $this->dinero($totalPagadoGeneral, $moneda),
                'total_pendiente_texto' => $this->dinero($totalPendienteGeneral, $moneda),
                'total_comision_texto' => $this->dinero($totalComisionGeneral, $moneda),
            ],
            'empleados' => $reporteEmpleados,
        ]);
    }

    public function extractoMensual(Request $request)
    {
        Carbon::setLocale('es');

        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?: 'Bs';

        $anio = (int) $request->get('anio', Carbon::now('America/La_Paz')->year);
        $mes = (int) $request->get('mes', Carbon::now('America/La_Paz')->month);

        if ($mes < 1 || $mes > 12) return response()->json(['message' => 'Mes inválido.'], 422);
        if ($anio < 2000 || $anio > 2100) return response()->json(['message' => 'Año inválido.'], 422);

        $inicio = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/La_Paz')->startOfMonth();
        $fin = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/La_Paz')->endOfMonth();

        $citas = Cita::with(['cliente', 'empleado', 'pagos'])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->get();

        $totalEstimado = (float) $citas->sum(fn ($cita) => (float) ($cita->precio ?? 0));
        $totalPagado = $this->totalPagadoDesdeCitas($citas);
        $totalPendiente = max($totalEstimado - $totalPagado, 0);

        $citasConcluidas = $citas->where('estado', 'concluida')->count();
        $citasPendientes = $citas->where('estado', 'pendiente')->count();
        $citasCanceladas = $citas->where('estado', 'cancelada')->count();

        $resumenDias = [];

        for ($dia = 1; $dia <= $fin->day; $dia++) {
            $fechaDia = Carbon::create($anio, $mes, $dia, 0, 0, 0, 'America/La_Paz')->toDateString();
            $citasDia = $citas->filter(fn ($cita) => Carbon::parse($cita->fecha)->toDateString() === $fechaDia);

            $totalEstimadoDia = (float) $citasDia->sum(fn ($cita) => (float) ($cita->precio ?? 0));
            $totalPagadoDia = $this->totalPagadoDesdeCitas($citasDia);
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
            'fecha_generado' => Carbon::now('America/La_Paz')->format('d/m/Y H:i'),
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

        $pdf = Pdf::loadView('pdf.extracto-mensual', $datos)->setPaper('a4', 'portrait');

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
            ? Carbon::parse($request->get('fecha'), 'America/La_Paz')->toDateString()
            : Carbon::now('America/La_Paz')->toDateString();

        $fechaCarbon = Carbon::parse($fecha, 'America/La_Paz');

        $citas = Cita::with(['cliente', 'empleado', 'pagos'])
            ->whereDate('fecha', $fecha)
            ->orderBy('hora', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $pagosDetalle = collect();

        foreach ($citas->where('estado_pago', 'pagado') as $cita) {
            $cliente = $cita->cliente;
            $precio = (float) ($cita->precio ?? 0);
            $pagosCita = $cita->pagos ?? collect();
            $primerPago = $pagosCita->sortBy('fecha_pago')->first();
            $metodo = $primerPago?->metodo ?: ($cita->metodo_pago ?: 'otros');

            $fechaPago = $primerPago?->fecha_pago
                ? Carbon::parse($primerPago->fecha_pago)
                : Carbon::parse($fecha . ' ' . substr((string) ($cita->hora ?: '00:00'), 0, 5), 'America/La_Paz');

            $pagosDetalle->push([
                'id' => 'cita-pagada-' . $cita->id,
                'pago_id' => $primerPago?->id,
                'cita_id' => $cita->id,
                'fecha_pago' => $fechaPago->toDateTimeString(),
                'hora_pago' => $cita->hora ? substr((string) $cita->hora, 0, 5) : $fechaPago->format('H:i'),
                'monto' => round($precio, 2),
                'monto_texto' => $this->dinero($precio, $moneda),
                'metodo' => $metodo,
                'estado' => 'pagado',
                'cliente' => $cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cliente?->telefono ?? '',
                'empleado' => $cita->empleado?->nombre ?? 'Sin empleado',
                'servicio' => $cita->servicio ?? 'Sin servicio',
                'fecha_cita' => $cita->fecha ?? '',
                'hora_cita' => $cita->hora ?? '',
                'origen' => $pagosCita->count() > 0 ? 'pago_vinculado' : 'cita_pagada',
                'observacion' => $pagosCita->count() > 0
                    ? 'Cita pagada con registro de pago vinculado.'
                    : 'Cita marcada como pagada sin registro individual de pago.',
            ]);
        }

        $pagosDetalle = $pagosDetalle->sortBy([['hora_pago', 'asc'], ['id', 'asc']])->values();
        $totalCobrado = (float) $pagosDetalle->sum(fn ($pago) => (float) ($pago['monto'] ?? 0));

        $resumenMetodos = $this->resumenMetodosBase($moneda);

        foreach ($pagosDetalle as $pago) {
            $categoria = $this->categoriaMetodo($pago['metodo'] ?? 'otros');
            if (!isset($resumenMetodos[$categoria])) $categoria = 'otros';

            $resumenMetodos[$categoria]['cantidad']++;
            $resumenMetodos[$categoria]['total'] += (float) ($pago['monto'] ?? 0);
        }

        foreach ($resumenMetodos as $clave => $datosMetodo) {
            $resumenMetodos[$clave]['total'] = round((float) $datosMetodo['total'], 2);
            $resumenMetodos[$clave]['total_texto'] = $this->dinero($datosMetodo['total'], $moneda);
        }

        $citasDetalle = $citas->map(function ($cita) use ($moneda) {
            $precio = (float) ($cita->precio ?? 0);
            $totalPagado = (($cita->estado_pago ?? null) === 'pagado')
                ? $precio
                : (float) $cita->pagos->sum('monto');
            $pendiente = max($precio - $totalPagado, 0);

            return [
                'id' => $cita->id,
                'fecha' => $cita->fecha,
                'hora' => $cita->hora,
                'cliente' => $cita->cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cita->cliente?->telefono ?? '',
                'empleado' => $cita->empleado?->nombre ?? 'Sin empleado',
                'servicio' => $cita->servicio ?? 'Sin servicio',
                'estado' => $cita->estado ?? 'pendiente',
                'estado_pago' => $cita->estado_pago ?? 'pendiente',
                'precio' => round($precio, 2),
                'precio_texto' => $this->dinero($precio, $moneda),
                'total_pagado' => round($totalPagado, 2),
                'total_pagado_texto' => $this->dinero($totalPagado, $moneda),
                'pendiente' => round($pendiente, 2),
                'pendiente_texto' => $this->dinero($pendiente, $moneda),
            ];
        })->values();

        $cantidadPagos = $pagosDetalle->count();
        $ticketPromedio = $cantidadPagos > 0 ? $totalCobrado / $cantidadPagos : 0;

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
            'fecha_generado' => Carbon::now('America/La_Paz')->format('d/m/Y H:i'),
            'total_cobrado' => round($totalCobrado, 2),
            'total_cobrado_texto' => $this->dinero($totalCobrado, $moneda),
            'cantidad_pagos' => $cantidadPagos,
            'ticket_promedio' => round($ticketPromedio, 2),
            'ticket_promedio_texto' => $this->dinero($ticketPromedio, $moneda),
            'citas_dia' => $citas->count(),
            'citas_pagadas' => $citas->where('estado_pago', 'pagado')->count(),
            'citas_pendientes_pago' => $citas->where('estado_pago', 'pendiente')->count(),
            'citas_pendientes' => $citas->where('estado', 'pendiente')->count(),
            'citas_concluidas' => $citas->where('estado', 'concluida')->count(),
            'citas_canceladas' => $citas->where('estado', 'cancelada')->count(),
            'metodos' => $resumenMetodos,
            'pagos' => $pagosDetalle,
            'citas' => $citasDetalle,
        ];

        $pdf = Pdf::loadView('pdf.caja-diaria', $datos)->setPaper('a4', 'portrait');

        $nombreSistema = Str::slug($configuracion['nombre_corto'] ?: 'aurea_beauty');
        $nombreArchivo = 'caja_diaria_' . $nombreSistema . '_' . $fecha . '.pdf';

        return $pdf->download($nombreArchivo);
    }
}
