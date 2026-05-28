<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            // Si la tabla aún no existe o Render está aplicando migraciones,
            // usamos valores base para no romper pagos ni facturas.
        }

        return $this->valoresBaseConfiguracion();
    }

    private function dinero($monto, ?string $moneda = null): string
    {
        $configuracion = $this->obtenerConfiguracionNegocio();
        $monedaUsar = $moneda ?: ($configuracion['moneda'] ?? 'Bs');

        return trim($monedaUsar . ' ' . number_format((float) $monto, 2, '.', ','));
    }

    private function actualizarEstadoPagoCita(Cita $cita): void
    {
        $cita->loadMissing('pagos');

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
        $configuracion = $this->obtenerConfiguracionNegocio();

        $pagos = Pago::with('cita.cliente')
            ->orderBy('fecha_pago', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'pagos' => $pagos,
            'configuracion' => $configuracion,
            'moneda' => $configuracion['moneda'] ?? 'Bs',
        ]);
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
        $configuracion = $this->obtenerConfiguracionNegocio();

        $pagos = Pago::with('cita.cliente')
            ->where('cita_id', $cita_id)
            ->orderBy('fecha_pago', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'pagos' => $pagos,
            'configuracion' => $configuracion,
            'moneda' => $configuracion['moneda'] ?? 'Bs',
        ]);
    }

    public function factura($id)
    {
        $configuracion = $this->obtenerConfiguracionNegocio();

        $pago = Pago::with('cita.cliente')->findOrFail($id);

        return response()->json([
            'pago' => $pago,
            'configuracion' => $configuracion,
            'monto_texto' => $this->dinero($pago->monto, $configuracion['moneda'] ?? 'Bs'),
            'negocio' => [
                'nombre_negocio' => $configuracion['nombre_negocio'] ?? 'AUREA Beauty Salon',
                'nombre_corto' => $configuracion['nombre_corto'] ?? 'AUREA Beauty',
                'slogan' => $configuracion['slogan'] ?? '',
                'telefono' => $configuracion['telefono'] ?? '',
                'whatsapp' => $configuracion['whatsapp'] ?? '',
                'direccion' => $configuracion['direccion'] ?? '',
                'logo_url' => $configuracion['logo_url'] ?? '',
                'moneda' => $configuracion['moneda'] ?? 'Bs',
            ],
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