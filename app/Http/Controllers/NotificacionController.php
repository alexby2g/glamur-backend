<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index()
    {
        $notificaciones = Notificacion::orderBy('created_at', 'desc')
            ->limit(25)
            ->get();

        $noLeidas = Notificacion::where('leido', false)->count();

        return response()->json([
            'notificaciones' => $notificaciones,
            'no_leidas' => $noLeidas,
        ]);
    }

    public function marcarLeida($id)
    {
        $notificacion = Notificacion::findOrFail($id);

        $notificacion->update([
            'leido' => true,
        ]);

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'notificacion' => $notificacion,
        ]);
    }

    public function marcarTodasLeidas()
    {
        Notificacion::where('leido', false)->update([
            'leido' => true,
        ]);

        return response()->json([
            'message' => 'Todas las notificaciones fueron marcadas como leídas.',
        ]);
    }

    public function limpiar()
    {
        Notificacion::truncate();

        return response()->json([
            'message' => 'Notificaciones limpiadas correctamente.',
        ]);
    }
}