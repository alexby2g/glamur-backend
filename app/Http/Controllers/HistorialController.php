<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cita;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistorialController extends Controller
{
    // =====================================================
    // 📜 LISTAR HISTORIAL DE ELIMINADOS
    // URL: GET /api/historial/eliminados
    // =====================================================
    public function index()
    {
        $clientesEliminados = Cliente::onlyTrashed()
            ->with([
                'todasLasCitas' => function ($query) {
                    $query->with(['pagos'])
                        ->orderBy('fecha', 'desc')
                        ->orderBy('hora', 'desc');
                }
            ])
            ->orderBy('deleted_at', 'desc')
            ->get();

        $citasEliminadas = Cita::onlyTrashed()
            ->with([
                'cliente',
                'pagos'
            ])
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json([
            'clientes_eliminados' => $clientesEliminados,
            'citas_eliminadas' => $citasEliminadas,
        ]);
    }

    // =====================================================
    // ♻️ RESTAURAR CLIENTE ELIMINADO
    // URL: PUT /api/historial/clientes/{id}/restaurar
    // =====================================================
    public function restaurarCliente($id)
    {
        DB::beginTransaction();

        try {
            $cliente = Cliente::onlyTrashed()->findOrFail($id);

            $cliente->restore();

            Cita::onlyTrashed()
                ->where('cliente_id', $cliente->id)
                ->restore();

            DB::commit();

            return response()->json([
                'message' => 'Cliente recuperado correctamente.',
                'cliente' => $cliente
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'message' => 'No se pudo recuperar el cliente.',
                'error' => $error->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // ♻️ RESTAURAR CITA / SERVICIO ELIMINADO
    // URL: PUT /api/historial/citas/{id}/restaurar
    // =====================================================
    public function restaurarCita($id)
    {
        DB::beginTransaction();

        try {
            $cita = Cita::onlyTrashed()->findOrFail($id);

            $cliente = Cliente::withTrashed()->find($cita->cliente_id);

            if ($cliente && $cliente->trashed()) {
                $cliente->restore();
            }

            $cita->restore();

            DB::commit();

            return response()->json([
                'message' => 'Cita recuperada correctamente.',
                'cita' => $cita
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'message' => 'No se pudo recuperar la cita.',
                'error' => $error->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // ♻️ RESTAURAR TODO EL HISTORIAL
    // URL: PUT /api/historial/restaurar-todo
    // =====================================================
    public function restaurarTodo()
    {
        DB::beginTransaction();

        try {
            Cliente::onlyTrashed()->restore();
            Cita::onlyTrashed()->restore();

            DB::commit();

            return response()->json([
                'message' => 'Todo el historial fue recuperado correctamente.'
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'message' => 'No se pudo recuperar todo el historial.',
                'error' => $error->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // 🗑️ LIMPIAR HISTORIAL DEFINITIVAMENTE
    // URL: DELETE /api/historial/limpiar
    // =====================================================
    public function limpiarHistorial()
    {
        DB::beginTransaction();

        try {
            $citasEliminadas = Cita::onlyTrashed()->get();

            foreach ($citasEliminadas as $cita) {
                Pago::where('cita_id', $cita->id)->delete();

                $cita->forceDelete();
            }

            $clientesEliminados = Cliente::onlyTrashed()->get();

            foreach ($clientesEliminados as $cliente) {
                $cliente->forceDelete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Historial limpiado definitivamente.'
            ]);
        } catch (\Throwable $error) {
            DB::rollBack();

            return response()->json([
                'message' => 'No se pudo limpiar el historial.',
                'error' => $error->getMessage()
            ], 500);
        }
    }
}