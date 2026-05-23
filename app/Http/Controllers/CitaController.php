<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CitaController extends Controller
{
    public function index()
    {
        return response()->json(
            Cita::with('cliente')->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->get()
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
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
            'fecha.required' => 'La fecha es obligatoria.',
            'hora.required' => 'La hora es obligatoria.',
            'hora.date_format' => 'La hora debe tener formato HH:MM.',
            'servicio.required' => 'El servicio es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $existe = Cita::where('fecha', $request->fecha)
            ->where('hora', $request->hora)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya existe una cita en esa fecha y hora.'], 422);
        }

        $cita = Cita::create([
            'cliente_id' => $request->cliente_id,
            'fecha' => $request->fecha,
            'hora' => $request->hora,
            'servicio' => trim($request->servicio),
            'precio' => $request->precio,
            'estado' => $request->estado ?? 'pendiente',
            'estado_pago' => $request->estado_pago ?? 'pendiente',
            'metodo_pago' => $request->metodo_pago,
        ]);

        return response()->json(['message' => 'Cita registrada correctamente', 'cita' => $cita->load('cliente')], 201);
    }

    public function update(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);

        if ($request->has('estado') && count($request->all()) === 1) {
            $request->validate(['estado' => 'required|in:pendiente,concluida,cancelada']);
            $cita->update(['estado' => $request->estado]);
            return response()->json(['message' => 'Estado actualizado correctamente', 'cita' => $cita->fresh('cliente')]);
        }

        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'servicio' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'estado' => 'nullable|in:pendiente,concluida,cancelada',
            'estado_pago' => 'nullable|in:pendiente,pagado',
            'metodo_pago' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $existe = Cita::where('fecha', $request->fecha)
            ->where('hora', $request->hora)
            ->where('id', '!=', $id)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya existe otra cita en ese horario.'], 422);
        }

        $cita->update($request->only([
            'cliente_id', 'fecha', 'hora', 'servicio', 'precio', 'estado', 'estado_pago', 'metodo_pago'
        ]));

        return response()->json(['message' => 'Cita actualizada correctamente', 'cita' => $cita->fresh('cliente')]);
    }

    public function finalizar($id)
    {
        $cita = Cita::findOrFail($id);
        $cita->update(['estado' => 'concluida']);

        return response()->json(['message' => 'Cita finalizada correctamente', 'cita' => $cita->fresh('cliente')]);
    }

    public function destroy($id)
    {
        Cita::findOrFail($id)->delete();
        return response()->json(['message' => 'Cita eliminada correctamente']);
    }

    public function dashboard()
    {
        return response()->json([
            'total' => Cita::count(),
            'pendientes' => Cita::where('estado', 'pendiente')->count(),
            'concluidas' => Cita::where('estado', 'concluida')->count(),
            'canceladas' => Cita::where('estado', 'cancelada')->count(),
            'ingreso_dia' => Cita::whereDate('fecha', now())->where('estado_pago', 'pagado')->sum('precio'),
            'ingreso_mes' => Cita::whereMonth('fecha', now()->month)->whereYear('fecha', now()->year)->where('estado_pago', 'pagado')->sum('precio'),
            'ingreso_anio' => Cita::whereYear('fecha', now()->year)->where('estado_pago', 'pagado')->sum('precio'),
        ]);
    }
}
