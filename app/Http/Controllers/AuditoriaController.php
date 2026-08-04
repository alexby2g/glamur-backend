<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'modulo' => 'nullable|string|max:80',
            'accion' => 'nullable|in:crear,actualizar,eliminar',
            'usuario_id' => 'nullable|integer|min:1',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'por_pagina' => 'nullable|integer|min:10|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Filtros inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Auditoria::query()->latest('id');

        $query->when($request->filled('modulo'), fn ($q) =>
            $q->where('modulo', $request->modulo)
        );

        $query->when($request->filled('accion'), fn ($q) =>
            $q->where('accion', $request->accion)
        );

        $query->when($request->filled('usuario_id'), fn ($q) =>
            $q->where('usuario_sistema_id', $request->usuario_id)
        );

        $query->when($request->filled('desde'), fn ($q) =>
            $q->whereDate('created_at', '>=', $request->desde)
        );

        $query->when($request->filled('hasta'), fn ($q) =>
            $q->whereDate('created_at', '<=', $request->hasta)
        );

        return response()->json(
            $query->paginate((int) $request->input('por_pagina', 25))
        );
    }
}
