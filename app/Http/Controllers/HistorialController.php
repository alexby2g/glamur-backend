<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;

class HistorialController extends Controller
{
    public function index(): JsonResponse
    {
        $clientesEliminados = Cliente::onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->get();

        $citasEliminadas = Cita::onlyTrashed()
            ->with([
                'cliente' => function ($query) {
                    $query->withTrashed();
                }
            ])
            ->orderBy('deleted_at', 'desc')
            ->get();

        $pagosEliminados = Pago::onlyTrashed()
            ->with([
                'cita' => function ($query) {
                    $query->withTrashed()->with([
                        'cliente' => function ($clienteQuery) {
                            $clienteQuery->withTrashed();
                        }
                    ]);
                }
            ])
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json([
            'clientes_eliminados' => $clientesEliminados,
            'citas_eliminadas' => $citasEliminadas,
            'pagos_eliminados' => $pagosEliminados,
        ]);
    }

    public function restaurarCliente($id): JsonResponse
    {
        $cliente = Cliente::onlyTrashed()->findOrFail($id);
        $cliente->restore();

        return response()->json([
            'message' => 'Cliente restaurado correctamente.',
            'cliente' => $cliente,
        ]);
    }

    public function restaurarCita($id): JsonResponse
    {
        $cita = Cita::onlyTrashed()->findOrFail($id);

        if ($cita->cliente_id) {
            $cliente = Cliente::withTrashed()->find($cita->cliente_id);

            if ($cliente && $cliente->trashed()) {
                $cliente->restore();
            }
        }

        $cita->restore();

        return response()->json([
            'message' => 'Cita restaurada correctamente.',
            'cita' => $cita,
        ]);
    }

    public function restaurarPago($id): JsonResponse
    {
        $pago = Pago::onlyTrashed()->findOrFail($id);

        if ($pago->cita_id) {
            $cita = Cita::withTrashed()->find($pago->cita_id);

            if ($cita) {
                if ($cita->cliente_id) {
                    $cliente = Cliente::withTrashed()->find($cita->cliente_id);

                    if ($cliente && $cliente->trashed()) {
                        $cliente->restore();
                    }
                }

                if ($cita->trashed()) {
                    $cita->restore();
                }
            }
        }

        $pago->restore();

        return response()->json([
            'message' => 'Pago restaurado correctamente.',
            'pago' => $pago,
        ]);
    }

    public function restaurarTodo(): JsonResponse
    {
        Cliente::onlyTrashed()->restore();
        Cita::onlyTrashed()->restore();
        Pago::onlyTrashed()->restore();

        return response()->json([
            'message' => 'Todos los elementos fueron restaurados correctamente.',
        ]);
    }

    public function limpiarHistorial(): JsonResponse
    {
        Pago::onlyTrashed()->forceDelete();
        Cita::onlyTrashed()->forceDelete();
        Cliente::onlyTrashed()->forceDelete();

        return response()->json([
            'message' => 'Historial limpiado correctamente.',
        ]);
    }
}
