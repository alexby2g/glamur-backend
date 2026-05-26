<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Middleware\VerificarTokenSistema;

/*
|--------------------------------------------------------------------------
| API ROUTES GLAMUR
|--------------------------------------------------------------------------
*/

// =====================================================
// 🔐 AUTENTICACIÓN PÚBLICA
// =====================================================
// Estas rutas son públicas porque sirven para entrar o registrarse.
// El registro queda protegido por el código secreto en AuthController.
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);


// =====================================================
// 🔒 RUTAS PROTEGIDAS CON TOKEN
// =====================================================
Route::middleware([VerificarTokenSistema::class])->group(function () {

    // =====================================================
    // 🔐 SESIÓN
    // =====================================================
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);


    // =====================================================
    // 📊 DASHBOARD
    // =====================================================
    Route::get('/dashboard', [CitaController::class, 'dashboard']);


    // =====================================================
    // 👤 CLIENTES
    // =====================================================
    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::put('/clientes/{id}', [ClienteController::class, 'update']);
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);

    Route::get('/clientes/historial/buscar', [ClienteController::class, 'historial']);


    // =====================================================
    // 📅 CITAS
    // =====================================================
    Route::get('/citas', [CitaController::class, 'index']);
    Route::post('/citas', [CitaController::class, 'store']);
    Route::put('/citas/{id}', [CitaController::class, 'update']);
    Route::put('/citas/finalizar/{id}', [CitaController::class, 'finalizar']);
    Route::delete('/citas/{id}', [CitaController::class, 'destroy']);


    // =====================================================
    // 💰 PAGOS
    // =====================================================
    Route::get('/pagos', [PagoController::class, 'index']);
    Route::post('/pagos', [PagoController::class, 'store']);
    Route::get('/pagos/historial/{cita_id}', [PagoController::class, 'historial']);
    Route::get('/pagos/factura/{id}', [PagoController::class, 'factura']);
    Route::delete('/pagos/{id}', [PagoController::class, 'destroy']);


    // =====================================================
    // 💅 SERVICIOS / COMBOS
    // =====================================================
    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::post('/servicios/cargar-base', [ServicioController::class, 'cargarBase']);
    Route::post('/servicios', [ServicioController::class, 'store']);
    Route::put('/servicios/{id}', [ServicioController::class, 'update']);
    Route::delete('/servicios/{id}', [ServicioController::class, 'destroy']);


    // =====================================================
    // 🔔 NOTIFICACIONES
    // =====================================================
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::put('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::put('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);
    Route::delete('/notificaciones/limpiar', [NotificacionController::class, 'limpiar']);


        // =====================================================
    // 🗑️ HISTORIAL / RECUPERACIÓN
    // =====================================================
    Route::get('/historial/eliminados', [HistorialController::class, 'index']);

    Route::put('/historial/clientes/{id}/restaurar', [HistorialController::class, 'restaurarCliente']);
    Route::put('/historial/citas/{id}/restaurar', [HistorialController::class, 'restaurarCita']);
    Route::put('/historial/pagos/{id}/restaurar', [HistorialController::class, 'restaurarPago']);

    Route::put('/historial/restaurar-todo', [HistorialController::class, 'restaurarTodo']);

    // Eliminar definitivamente por separado
    Route::delete('/historial/clientes/{id}/eliminar', [HistorialController::class, 'eliminarClienteDefinitivo']);
    Route::delete('/historial/citas/{id}/eliminar', [HistorialController::class, 'eliminarCitaDefinitiva']);
    Route::delete('/historial/pagos/{id}/eliminar', [HistorialController::class, 'eliminarPagoDefinitivo']);

    // Limpiar todo el historial
    Route::delete('/historial/limpiar', [HistorialController::class, 'limpiarHistorial']);

        // =====================================================
        // 📄 REPORTES PDF
        // =====================================================
        Route::get('/reportes/extracto-mensual', [ReporteController::class, 'extractoMensual']);

    });