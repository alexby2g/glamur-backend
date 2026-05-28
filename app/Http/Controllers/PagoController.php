<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Configuracion;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
                return array_merge(
                    $this->valoresBaseConfiguracion(),
                    $configuracion->toArray()
                );
            }
        } catch (\Throwable $error) {
            // Si la tabla aún no existe, usamos valores base para no romper pagos.
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

    private function actualizarEstadoPagoCita(Cita $cita): void
    {
        $precio = (float) ($cita->precio ?? 0);
        $totalPagado = (float) $cita->pagos()->sum('monto');

        if ($precio <= 0) {
            $estadoPago = $totalPagado > 0 ? 'pagado' : 'pendiente';
        } else {
            $estadoPago = $totalPagado >= $precio ? 'pagado' : 'pendiente';
        }

        $cita->update([
            'estado_pago' => $estadoPago,
        ]);
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

        $configuracion = $this->obtenerConfiguracionNegocio();
        $moneda = $configuracion['moneda'] ?? 'Bs';
        $nombreCorto = $configuracion['nombre_corto'] ?? 'AUREA Beauty';

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
        $montoTexto = $this->dinero($pago->monto, $moneda);

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
                'monto_texto' => $montoTexto,
                'moneda' => $moneda,
                'metodo' => $pago->metodo,
                'fecha_pago' => $pago->fecha_pago,
                'negocio' => $nombreCorto,
            ],
        ]);

        return response()->json([
            'message' => 'Pago registrado correctamente',
            'pago' => $pago->load('cita.cliente'),
            'monto_texto' => $montoTexto,
            'configuracion' => $configuracion,
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
        ], [
            'fecha.date' => 'La fecha enviada no es válida.',
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
            ? Carbon::parse($request->get('fecha'))->toDateString()
            : now()->toDateString();

        $fechaCarbon = Carbon::parse($fecha);

        $pagos = Pago::with('cita.cliente')
            ->whereDate('fecha_pago', $fecha)
            ->orderBy('fecha_pago', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $citasDia = Cita::with(['cliente', 'pagos'])
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

        foreach ($resumenMetodos as $clave => $datos) {
            $resumenMetodos[$clave]['total'] = round((float) $datos['total'], 2);
            $resumenMetodos[$clave]['total_texto'] = $this->dinero($datos['total'], $moneda);
        }

        $citasPagadas = $citasDia->filter(function ($cita) {
            $precio = (float) ($cita->precio ?? 0);
            $totalPagado = (float) $cita->pagos->sum('monto');

            return $totalPagado > 0 && ($precio <= 0 || $totalPagado >= $precio || $cita->estado_pago === 'pagado');
        })->count();

        $citasPendientesPago = $citasDia->filter(function ($cita) {
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
                'cita_id' => $pago->cita_id,
                'cliente' => $cliente?->nombre ?? 'Cliente no registrado',
                'telefono' => $cliente?->telefono ?? '',
                'servicio' => $cita?->servicio ?? 'Sin servicio',
                'fecha_cita' => $cita?->fecha ?? '',
                'hora_cita' => $cita?->hora ?? '',
            ];
        })->values();

        $citasDetalle = $citasDia->map(function ($cita) use ($moneda) {
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

        return response()->json([
            'fecha' => $fecha,
            'fecha_texto' => ucfirst($fechaCarbon->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
            'configuracion' => $configuracion,
            'moneda' => $moneda,

            'resumen' => [
                'total_cobrado' => round($totalCobrado, 2),
                'total_cobrado_texto' => $this->dinero($totalCobrado, $moneda),
                'cantidad_pagos' => $pagos->count(),
                'ticket_promedio' => round($ticketPromedio, 2),
                'ticket_promedio_texto' => $this->dinero($ticketPromedio, $moneda),

                'citas_dia' => $citasDia->count(),
                'citas_pagadas' => $citasPagadas,
                'citas_pendientes_pago' => $citasPendientesPago,

                'citas_pendientes' => $citasDia->where('estado', 'pendiente')->count(),
                'citas_concluidas' => $citasDia->where('estado', 'concluida')->count(),
                'citas_canceladas' => $citasDia->where('estado', 'cancelada')->count(),
            ],

            'metodos' => $resumenMetodos,
            'pagos' => $pagosDetalle,
            'citas' => $citasDetalle,
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