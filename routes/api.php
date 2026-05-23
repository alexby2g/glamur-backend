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

// =====================
// 👤 CLIENTES
// =====================
Route::get('/clientes', [ClienteController::class, 'index']);
Route::post('/clientes', [ClienteController::class, 'store']);
Route::put('/clientes/{id}', [ClienteController::class, 'update']);
Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);


// =====================
// 📅 CITAS
// =====================
Route::get('/citas', [CitaController::class, 'index']);
Route::post('/citas', [CitaController::class, 'store']);
Route::put('/citas/{id}', [CitaController::class, 'update']);

// 🔥 OPCIONAL (solo si tienes ese método en tu controller)
Route::put('/citas/finalizar/{id}', [CitaController::class, 'finalizar']);

Route::delete('/citas/{id}', [CitaController::class, 'destroy']);


// =====================
// 💰 PAGOS
// =====================
Route::get('/pagos', [PagoController::class, 'index']);
Route::post('/pagos', [PagoController::class, 'store']);

// 📜 Historial por cita
Route::get('/pagos/historial/{cita_id}', [PagoController::class, 'historial']);

// 🧾 Factura
Route::get('/pagos/factura/{id}', [PagoController::class, 'factura']);

// ❌ Eliminar
Route::delete('/pagos/{id}', [PagoController::class, 'destroy']);


// =====================
// 📊 DASHBOARD
// =====================
Route::get('/dashboard', [CitaController::class, 'dashboard']);