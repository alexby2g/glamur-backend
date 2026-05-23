<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    public function index()
    {
        return response()->json(Cliente::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $cliente = Cliente::create([
            'nombre' => trim($request->nombre),
            'telefono' => $request->telefono,
            'email' => $request->email,
        ]);

        return response()->json(['message' => 'Cliente registrado correctamente', 'cliente' => $cliente], 201);
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $cliente->update([
            'nombre' => trim($request->nombre),
            'telefono' => $request->telefono,
            'email' => $request->email,
        ]);

        return response()->json(['message' => 'Cliente actualizado correctamente', 'cliente' => $cliente]);
    }

    public function destroy($id)
    {
        Cliente::findOrFail($id)->delete();
        return response()->json(['message' => 'Cliente eliminado correctamente']);
    }
}
