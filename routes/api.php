<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PagoController;

/*
|--------------------------------------------------------------------------
| API ROUTES LIMPIAS
|--------------------------------------------------------------------------
*/

// =====================================================
// 👤 CLIENTES
// =====================================================

// 📋 LISTAR
Route::get('/clientes', [ClienteController::class, 'index']);

// ➕ REGISTRAR
Route::post('/clientes', [ClienteController::class, 'store']);

// ✏️ ACTUALIZAR
Route::put('/clientes/{id}', [ClienteController::class, 'update']);

// ❌ ELIMINAR
Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);

// 📜 HISTORIAL DEL CLIENTE
// Buscar por nombre o teléfono
// Ejemplo:
// /api/clientes/historial/buscar?buscar=juan
// /api/clientes/historial/buscar?buscar=76543210
Route::get('/clientes/historial/buscar', [ClienteController::class, 'historial']);


// =====================================================
// 📅 CITAS
// =====================================================

// 📋 LISTAR
Route::get('/citas', [CitaController::class, 'index']);

// ➕ REGISTRAR
Route::post('/citas', [CitaController::class, 'store']);

// ✏️ ACTUALIZAR
Route::put('/citas/{id}', [CitaController::class, 'update']);

// ✅ FINALIZAR
Route::put('/citas/finalizar/{id}', [CitaController::class, 'finalizar']);

// ❌ ELIMINAR
Route::delete('/citas/{id}', [CitaController::class, 'destroy']);


// =====================================================
// 💰 PAGOS
// =====================================================

// 📋 LISTAR
Route::get('/pagos', [PagoController::class, 'index']);

// ➕ REGISTRAR
Route::post('/pagos', [PagoController::class, 'store']);

// 📜 HISTORIAL POR CITA
Route::get('/pagos/historial/{cita_id}', [PagoController::class, 'historial']);

// 🧾 FACTURA
Route::get('/pagos/factura/{id}', [PagoController::class, 'factura']);

// ❌ ELIMINAR
Route::delete('/pagos/{id}', [PagoController::class, 'destroy']);


// =====================================================
// 📊 DASHBOARD
// =====================================================

Route::get('/dashboard', [CitaController::class, 'dashboard']);