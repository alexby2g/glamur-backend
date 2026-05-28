<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CitaController extends Controller
{
    public function index()
    {
        return response()->json(
            Cita::with(['cliente', 'empleado'])
                ->orderBy('fecha', 'desc')
                ->orderBy('hora', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'empleado_id' => 'nullable|exists:empleados,id',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'servicio' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'estado' => 'nullable|in:pendiente,concluida,cancelada',
            'estado_pago' => 'nullable|in:pendiente,pagado',
            'metodo_pago' => 'nullable|string|max:50',
        ], [
            'cliente_id.required' => 'Debe seleccionar un cliente.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'empleado_id.exists' => 'El empleado seleccionado no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'hora.required' => 'La hora es obligatoria.',
            'hora.date_format' => 'La hora debe tener formato HH:MM.',
            'servicio.required' => 'El servicio es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existe = Cita::where('fecha', $request->fecha)
            ->where('hora', $request->hora)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya existe una cita en esa fecha y hora.',
            ], 422);
        }

        $cita = Cita::create([
            'cliente_id' => $request->cliente_id,
            'empleado_id' => $request->empleado_id ?: null,
            'fecha' => $request->fecha,
            'hora' => $request->hora,
            'servicio' => trim($request->servicio),
            'precio' => $request->precio,
            'estado' => $request->estado ?? 'pendiente',
            'estado_pago' => $request->estado_pago ?? 'pendiente',
            'metodo_pago' => $request->metodo_pago,
        ]);

        return response()->json([
            'message' => 'Cita registrada correctamente',
            'cita' => $cita->load(['cliente', 'empleado']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);

        if ($request->has('estado') && count($request->all()) === 1) {
            $request->validate([
                'estado' => 'required|in:pendiente,concluida,cancelada',
            ]);

            $cita->update([
                'estado' => $request->estado,
            ]);

            return response()->json([
                'message' => 'Estado actualizado correctamente',
                'cita' => $cita->fresh(['cliente', 'empleado']),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'empleado_id' => 'nullable|exists:empleados,id',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'servicio' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'estado' => 'nullable|in:pendiente,concluida,cancelada',
            'estado_pago' => 'nullable|in:pendiente,pagado',
            'metodo_pago' => 'nullable|string|max:50',
        ], [
            'cliente_id.required' => 'Debe seleccionar un cliente.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'empleado_id.exists' => 'El empleado seleccionado no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'hora.required' => 'La hora es obligatoria.',
            'hora.date_format' => 'La hora debe tener formato HH:MM.',
            'servicio.required' => 'El servicio es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $existe = Cita::where('fecha', $request->fecha)
            ->where('hora', $request->hora)
            ->where('id', '!=', $id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya existe otra cita en ese horario.',
            ], 422);
        }

        $cita->update([
            'cliente_id' => $request->cliente_id,
            'empleado_id' => $request->empleado_id ?: null,
            'fecha' => $request->fecha,
            'hora' => $request->hora,
            'servicio' => trim($request->servicio),
            'precio' => $request->precio,
            'estado' => $request->estado ?? $cita->estado,
            'estado_pago' => $request->estado_pago ?? $cita->estado_pago,
            'metodo_pago' => $request->metodo_pago,
        ]);

        return response()->json([
            'message' => 'Cita actualizada correctamente',
            'cita' => $cita->fresh(['cliente', 'empleado']),
        ]);
    }

    public function finalizar($id)
    {
        $cita = Cita::findOrFail($id);

        $cita->update([
            'estado' => 'concluida',
        ]);

        return response()->json([
            'message' => 'Cita finalizada correctamente',
            'cita' => $cita->fresh(['cliente', 'empleado']),
        ]);
    }

    public function destroy($id)
    {
        Cita::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Cita eliminada correctamente',
        ]);
    }

    public function dashboard()
    {
        $hoy = Carbon::now();
        $inicioMes = $hoy->copy()->startOfMonth();
        $finMes = $hoy->copy()->endOfMonth();
        $inicioAnio = $hoy->copy()->startOfYear();
        $finAnio = $hoy->copy()->endOfYear();

        $sumarIngresos = function ($citas) {
            return (float) $citas
                ->where('estado_pago', 'pagado')
                ->sum('precio');
        };

        $citasMes = Cita::with(['cliente', 'empleado'])
            ->whereBetween('fecha', [
                $inicioMes->toDateString(),
                $finMes->toDateString(),
            ])
            ->get();

        $citasPorDia = $citasMes->groupBy(function ($cita) {
            return Carbon::parse($cita->fecha)->format('Y-m-d');
        });

        $estadisticasDias = [];

        for ($fecha = $inicioMes->copy(); $fecha->lte($finMes); $fecha->addDay()) {
            $clave = $fecha->format('Y-m-d');
            $grupo = $citasPorDia->get($clave, collect());

            $estadisticasDias[] = [
                'fecha' => $clave,
                'label' => $fecha->format('d/m'),
                'citas' => $grupo->count(),
                'ingresos' => $sumarIngresos($grupo),
            ];
        }

        $meses = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ];

        $citasAnio = Cita::with(['cliente', 'empleado'])
            ->whereBetween('fecha', [
                $inicioAnio->toDateString(),
                $finAnio->toDateString(),
            ])
            ->get();

        $citasPorMes = $citasAnio->groupBy(function ($cita) {
            return (int) Carbon::parse($cita->fecha)->format('m');
        });

        $estadisticasMeses = [];

        foreach ($meses as $numeroMes => $nombreMes) {
            $grupo = $citasPorMes->get($numeroMes, collect());

            $estadisticasMeses[] = [
                'mes' => $numeroMes,
                'label' => $nombreMes,
                'citas' => $grupo->count(),
                'ingresos' => $sumarIngresos($grupo),
            ];
        }

        $serviciosTop = Cita::all()
            ->groupBy(function ($cita) {
                return $cita->servicio ?: 'Sin servicio';
            })
            ->map(function ($grupo, $servicio) use ($sumarIngresos) {
                return [
                    'servicio' => $servicio,
                    'cantidad' => $grupo->count(),
                    'ingresos' => $sumarIngresos($grupo),
                ];
            })
            ->sortByDesc('cantidad')
            ->values()
            ->take(5);

        $empleadosTop = Cita::with('empleado')
            ->whereNotNull('empleado_id')
            ->get()
            ->groupBy(function ($cita) {
                return $cita->empleado?->nombre ?: 'Sin empleado';
            })
            ->map(function ($grupo, $empleado) use ($sumarIngresos) {
                return [
                    'empleado' => $empleado,
                    'cantidad' => $grupo->count(),
                    'ingresos' => $sumarIngresos($grupo),
                ];
            })
            ->sortByDesc('cantidad')
            ->values()
            ->take(5);

        $ultimasCitas = Cita::with(['cliente', 'empleado'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'total' => Cita::count(),
            'citas_hoy' => Cita::whereDate('fecha', $hoy->toDateString())->count(),
            'citas_mes' => $citasMes->count(),
            'citas_anio' => $citasAnio->count(),

            'pendientes' => Cita::where('estado', 'pendiente')->count(),
            'concluidas' => Cita::where('estado', 'concluida')->count(),
            'canceladas' => Cita::where('estado', 'cancelada')->count(),

            'clientes_total' => Cliente::count(),

            'ingreso_dia' => Cita::whereDate('fecha', $hoy->toDateString())
                ->where('estado_pago', 'pagado')
                ->sum('precio'),

            'ingreso_mes' => Cita::whereBetween('fecha', [
                $inicioMes->toDateString(),
                $finMes->toDateString(),
            ])
                ->where('estado_pago', 'pagado')
                ->sum('precio'),

            'ingreso_anio' => Cita::whereBetween('fecha', [
                $inicioAnio->toDateString(),
                $finAnio->toDateString(),
            ])
                ->where('estado_pago', 'pagado')
                ->sum('precio'),

            'estadisticas_dias' => $estadisticasDias,
            'estadisticas_meses' => $estadisticasMeses,
            'servicios_top' => $serviciosTop,
            'empleados_top' => $empleadosTop,
            'ultimas_citas' => $ultimasCitas,
        ]);
    }
}