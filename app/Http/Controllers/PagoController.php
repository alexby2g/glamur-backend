<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Configuracion;
use App\Models\CierreCaja;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PagoController extends Controller
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
                return array_merge($this->valoresBaseConfiguracion(), $configuracion->toArray());
            }
        } catch (\Throwable $error) {
            //
        }

        return $this->valoresBaseConfiguracion();
    }

    private function dinero($monto, ?string $moneda = null): string
    {
        $configuracion = $this->obtenerConfiguracionNegocio();
        $monedaUsar = $moneda ?: ($configuracion['moneda'] ?? 'Bs');

        return trim($monedaUsar . ' ' . number_format((float) $monto, 2, '.', ','));
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
            'mixto' => ['label' => 'Mixto', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'transferencia' => ['label' => 'Transferencia', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'tarjeta' => ['label' => 'Tarjeta', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
            'otros' => ['label' => 'Otros', 'cantidad' => 0, 'total' => 0, 'total_texto' => $this->dinero(0, $moneda)],
        ];
    }

    private function actualizarEstadoPagoCita(Cita $cita): void
    {
        $precio = (float) ($cita->precio ?? 0);
        $totalPagado = (float) $cita->pagos()->sum('monto');

        $estadoPago = $precio <= 0
            ? ($totalPagado > 0 ? 'pagado' : 'pendiente')
            : ($totalPagado >= $precio ? 'pagado' : 'pendiente');

        $cita->update([
            'estado_pago' => $estadoPago,
        ]);
    }

    private function calcularMontosPago(Request $request): array
    {
        $metodo = $request->input('metodo');

        $montoEfectivo = 0;
        $montoQr = 0;
        $montoTransferencia = 0;

        if ($metodo === 'mixto') {
            $montoEfectivo = (float) ($request->input('monto_efectivo') ?? 0);
            $montoQr = (float) ($request->input('monto_qr') ?? 0);
            $montoTransferencia = (float) ($request->input('monto_transferencia') ?? 0);
            $montoTotal = $montoEfectivo + $montoQr + $montoTransferencia;
        } else {
            $montoTotal = (float) ($request->input('monto') ?? 0);

            if ($metodo === 'efectivo') $montoEfectivo = $montoTotal;
            if ($metodo === 'qr') $montoQr = $montoTotal;
            if ($metodo === 'transferencia') $montoTransferencia = $montoTotal;
        }

        return [
            'monto' => round($montoTotal, 2),
            'monto_efectivo' => round($montoEfectivo, 2),
            'monto_qr' => round($montoQr, 2),
            'monto_transferencia' => round($montoTransferencia, 2),
        ];
    }

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
            'metodo' => 'required|in:efectivo,qr,transferencia,mixto',
            'monto' => 'nullable|numeric|min:0',
            'monto_efectivo' => 'nullable|numeric|min:0',
            'monto_qr' => 'nullable|numeric|min:0',
            'monto_transferencia' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fechaCaja = Carbon::now('America/La_Paz')->toDateString();
        if (CierreCaja::whereDate('fecha', $fechaCaja)->where('estado', 'cerrada')->exists()) {
            return response()->json([
                'message' => 'La caja de hoy ya está cerrada. No se pueden registrar más pagos.',
            ], 409);
        }

        $montos = $this->calcularMontosPago($request);

        if ($montos['monto'] <= 0) {
            return response()->json([
                'message' => 'El monto total del pago debe ser mayor a 0.',
            ], 422);
        }

        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?? 'Bs';
        $nombreCorto = $configuracion['nombre_corto'] ?? 'AUREA Beauty';

        try {
            $resultado = DB::transaction(function () use ($request, $montos) {
                $cita = Cita::with('cliente')->findOrFail($request->cita_id);

                $pago = Pago::create([
                    'cita_id' => $cita->id,
                    'cliente_id' => $cita->cliente_id,
                    'monto' => $montos['monto'],
                    'monto_efectivo' => $montos['monto_efectivo'],
                    'monto_qr' => $montos['monto_qr'],
                    'monto_transferencia' => $montos['monto_transferencia'],
                    'metodo' => $request->metodo,
                    'estado' => 'pagado',
                    'fecha_pago' => now('America/La_Paz'),
                ]);

                $this->actualizarEstadoPagoCita($cita);

                $cita->update([
                    'metodo_pago' => $request->metodo,
                ]);

                return [
                    'pago' => $pago->refresh(),
                    'cita' => $cita->refresh()->load('cliente'),
                ];
            });

            $pago = $resultado['pago'];
            $cita = $resultado['cita'];

            $nombreCliente = $cita->cliente?->nombre ?? 'Cliente sin nombre';
            $servicio = $cita->servicio ?? 'Servicio';
            $montoTexto = $this->dinero($pago->monto, $moneda);

            $detallePago = $request->metodo === 'mixto'
                ? "Efectivo: {$this->dinero($montos['monto_efectivo'], $moneda)}, QR: {$this->dinero($montos['monto_qr'], $moneda)}, Transferencia: {$this->dinero($montos['monto_transferencia'], $moneda)}"
                : ucfirst($request->metodo);

            try {
                Notificacion::create([
                    'tipo' => 'pago',
                    'titulo' => 'Pago realizado',
                    'mensaje' => "Se registró un pago de {$montoTexto} de {$nombreCliente} en {$nombreCorto}.",
                    'data' => [
                        'pago_id' => $pago->id,
                        'cita_id' => $cita->id,
                        'cliente' => $nombreCliente,
                        'servicio' => $servicio,
                        'monto' => (float) $pago->monto,
                        'monto_efectivo' => (float) $pago->monto_efectivo,
                        'monto_qr' => (float) $pago->monto_qr,
                        'monto_transferencia' => (float) $pago->monto_transferencia,
                        'monto_texto' => $montoTexto,
                        'moneda' => $moneda,
                        'metodo' => $pago->metodo,
                        'detalle_pago' => $detallePago,
                        'fecha_pago' => $pago->fecha_pago ? Carbon::parse($pago->fecha_pago)->toDateTimeString() : null,
                        'negocio' => $nombreCorto,
                    ],
                ]);
            } catch (\Throwable $error) {
                Log::error('Error al crear notificación de pago', [
                    'error' => $error->getMessage(),
                    'pago_id' => $pago->id ?? null,
                    'cita_id' => $cita->id ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Pago registrado correctamente',
                'pago' => $pago->load('cita.cliente'),
                'monto_texto' => $montoTexto,
                'detalle_pago' => $detallePago,
                'configuracion' => $configuracion,
            ], 201);

        } catch (\Throwable $error) {
            Log::error('Error al registrar pago', [
                'error' => $error->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => 'No se pudo registrar el pago.',
                'error' => $error->getMessage(),
            ], 500);
        }
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
        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?? 'Bs';

        $pago = Pago::with('cita.cliente')->findOrFail($id);

        return response()->json([
            'pago' => $pago,
            'configuracion' => $configuracion,
            'monto_texto' => $this->dinero($pago->monto, $moneda),
            'negocio' => [
                'nombre_negocio' => $configuracion['nombre_negocio'] ?? 'AUREA Beauty Salon',
                'nombre_corto' => $configuracion['nombre_corto'] ?? 'AUREA Beauty',
                'slogan' => $configuracion['slogan'] ?? '',
                'telefono' => $configuracion['telefono'] ?? '',
                'whatsapp' => $configuracion['whatsapp'] ?? '',
                'direccion' => $configuracion['direccion'] ?? '',
                'logo_url' => $configuracion['logo_url'] ?? '',
                'moneda' => $moneda,
            ],
        ]);
    }

    public function cajaDiaria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        Carbon::setLocale('es');

        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?? 'Bs';

        $fecha = $request->get('fecha')
            ? Carbon::parse($request->get('fecha'), 'America/La_Paz')->toDateString()
            : Carbon::now('America/La_Paz')->toDateString();

        $fechaCarbon = Carbon::parse($fecha, 'America/La_Paz');

        $citasDia = Cita::with(['cliente', 'pagos'])
            ->whereDate('fecha', $fecha)
            ->orderBy('hora', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        /*
         * Caja diaria ahora se basa en las CITAS PAGADAS DEL DÍA.
         * Así coincide con Dashboard, Calendario e Ingresos por día.
         */
        $pagosNormalizados = collect();

        foreach ($citasDia->where('estado_pago', 'pagado') as $cita) {
            $cliente = $cita->cliente;
            $precioCita = (float) ($cita->precio ?? 0);
            $pagosCita = $cita->pagos ?? collect();

            $primerPago = $pagosCita->sortBy('fecha_pago')->first();

            $metodo = $primerPago?->metodo ?: ($cita->metodo_pago ?: 'otros');

            $montoEfectivo = (float) $pagosCita->sum('monto_efectivo');
            $montoQr = (float) $pagosCita->sum('monto_qr');
            $montoTransferencia = (float) $pagosCita->sum('monto_transferencia');

            if ($pagosCita->count() === 0) {
                if ($metodo === 'efectivo') $montoEfectivo = $precioCita;
                if ($metodo === 'qr') $montoQr = $precioCita;
                if ($metodo === 'transferencia') $montoTransferencia = $precioCita;
            }

            $fechaPago = $primerPago?->fecha_pago
                ? Carbon::parse($primerPago->fecha_pago)
                : Carbon::parse($fecha . ' ' . substr((string) ($cita->hora ?: '00:00'), 0, 5), 'America/La_Paz');

            $pagosNormalizados->push([
                'id' => 'cita-pagada-' . $cita->id,
                'pago_id' => $primerPago?->id,
                'cita_id' => $cita->id,
                'cliente_id' => $cliente?->id,
                'cliente' => $cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cliente?->telefono ?? '',
                'servicio' => $cita->servicio ?? 'Servicio no registrado',
                'metodo' => $metodo,
                'metodo_texto' => ucfirst(str_replace('_', ' ', $metodo)),
                'monto' => round($precioCita, 2),
                'monto_texto' => $this->dinero($precioCita, $moneda),
                'monto_efectivo' => round($montoEfectivo, 2),
                'monto_qr' => round($montoQr, 2),
                'monto_transferencia' => round($montoTransferencia, 2),
                'estado' => 'pagado',
                'fecha_pago' => $fechaPago->toDateTimeString(),
                'hora_pago' => $cita->hora ? substr((string) $cita->hora, 0, 5) : $fechaPago->format('H:i'),
                'origen' => $pagosCita->count() > 0 ? 'pago_vinculado' : 'cita_pagada',
                'vinculado' => $pagosCita->count() > 0,
                'pagos_registrados' => $pagosCita->count(),
                'observacion' => $pagosCita->count() > 0
                    ? 'Cita pagada con registro de pago vinculado.'
                    : 'Cita marcada como pagada sin registro individual de pago.',
            ]);
        }

        $pagosNormalizados = $pagosNormalizados
            ->sortBy([
                ['hora_pago', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $citasNormalizadas = $citasDia->map(function ($cita) use ($moneda) {
            $cliente = $cita->cliente;
            $precio = (float) ($cita->precio ?? 0);
            $pagado = $cita->estado_pago === 'pagado'
                ? $precio
                : (float) $cita->pagos->sum('monto');

            $pendiente = max($precio - $pagado, 0);

            return [
                'id' => $cita->id,
                'cita_id' => $cita->id,
                'cliente_id' => $cliente?->id,
                'cliente' => $cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cliente?->telefono ?? '',
                'servicio' => $cita->servicio ?? 'Servicio no registrado',
                'fecha' => $cita->fecha,
                'hora' => $cita->hora ? substr((string) $cita->hora, 0, 5) : '',
                'estado' => $cita->estado ?? 'pendiente',
                'estado_pago' => $cita->estado_pago ?? 'pendiente',
                'metodo_pago' => $cita->metodo_pago,
                'precio' => round($precio, 2),
                'precio_texto' => $this->dinero($precio, $moneda),
                'pagado' => round($pagado, 2),
                'pagado_texto' => $this->dinero($pagado, $moneda),
                'pendiente' => round($pendiente, 2),
                'pendiente_texto' => $this->dinero($pendiente, $moneda),
                'pagos_registrados' => $cita->pagos->count(),
            ];
        })->values();

        $totalCobrado = (float) $pagosNormalizados->sum(fn ($pago) => (float) ($pago['monto'] ?? 0));

        $resumenMetodos = $this->resumenMetodosBase($moneda);

        foreach ($pagosNormalizados as $pago) {
            $categoria = $this->categoriaMetodo($pago['metodo'] ?? 'otros');

            if (!isset($resumenMetodos[$categoria])) {
                $categoria = 'otros';
            }

            $resumenMetodos[$categoria]['cantidad']++;
            $resumenMetodos[$categoria]['total'] += (float) ($pago['monto'] ?? 0);

            if (($pago['metodo'] ?? '') === 'mixto') {
                $resumenMetodos['efectivo']['total'] += (float) ($pago['monto_efectivo'] ?? 0);
                $resumenMetodos['qr']['total'] += (float) ($pago['monto_qr'] ?? 0);
                $resumenMetodos['transferencia']['total'] += (float) ($pago['monto_transferencia'] ?? 0);
            }
        }

        foreach ($resumenMetodos as $clave => $datos) {
            $resumenMetodos[$clave]['total'] = round((float) $datos['total'], 2);
            $resumenMetodos[$clave]['total_texto'] = $this->dinero($datos['total'], $moneda);
        }

        $cantidadPagos = $pagosNormalizados->count();
        $ticketPromedio = $cantidadPagos > 0 ? $totalCobrado / $cantidadPagos : 0;

        return response()->json([
            'fecha' => $fecha,
            'fecha_texto' => ucfirst($fechaCarbon->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
            'configuracion' => $configuracion,
            'moneda' => $moneda,
            'resumen' => [
                'total_cobrado' => round($totalCobrado, 2),
                'total_cobrado_texto' => $this->dinero($totalCobrado, $moneda),
                'cantidad_pagos' => $cantidadPagos,
                'ticket_promedio' => round($ticketPromedio, 2),
                'ticket_promedio_texto' => $this->dinero($ticketPromedio, $moneda),
                'citas_dia' => $citasDia->count(),
                'citas_pagadas' => $citasDia->where('estado_pago', 'pagado')->count(),
                'citas_pendientes_pago' => $citasDia->where('estado_pago', 'pendiente')->count(),
                'citas_pendientes' => $citasDia->where('estado', 'pendiente')->count(),
                'citas_concluidas' => $citasDia->where('estado', 'concluida')->count(),
                'citas_canceladas' => $citasDia->where('estado', 'cancelada')->count(),
            ],
            'metodos' => $resumenMetodos,
            'pagos' => $pagosNormalizados,
            'citas' => $citasNormalizadas,
        ]);
    }

    public function destroy($id)
    {
        $pago = Pago::with('cita')->findOrFail($id);
        $cita = $pago->cita;

        $pago->delete();

        if ($cita) {
            $this->actualizarEstadoPagoCita($cita);
        }

        return response()->json([
            'message' => 'Pago eliminado correctamente',
        ]);
    }
}
