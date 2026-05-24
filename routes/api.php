<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SetupController;

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

// =====================
// 🗑️ HISTORIAL / RECUPERACIÓN
// =====================
Route::get('/historial/eliminados', [HistorialController::class, 'index']);

Route::put('/historial/clientes/{id}/restaurar', [HistorialController::class, 'restaurarCliente']);
Route::put('/historial/citas/{id}/restaurar', [HistorialController::class, 'restaurarCita']);

Route::put('/historial/restaurar-todo', [HistorialController::class, 'restaurarTodo']);

Route::delete('/historial/limpiar', [HistorialController::class, 'limpiarHistorial']);

// =====================
// 🔐 AUTENTICACIÓN
// =====================
Route::post('/login', [AuthController::class, 'login']);
Route::get('/me', [AuthController::class, 'me']);
Route::post('/logout', [AuthController::class, 'logout']);

// =====================
// ⚙️ SETUP TEMPORAL
// =====================
Route::get('/setup-admin', [SetupController::class, 'instalar']);

// =====================================================
// 📊 DASHBOARD
// =====================================================

Route::get('/dashboard', [CitaController::class, 'dashboard']);