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
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\SyncController;
use App\Http\Middleware\VerificarTokenSistema;
use App\Http\Middleware\VerificarRolSistema;

/*
|--------------------------------------------------------------------------
| API ROUTES GLAMUR / AUREA BEAUTY
|--------------------------------------------------------------------------
*/

// =====================================================
// 🔐 AUTENTICACIÓN PÚBLICA
// =====================================================
// Limita intentos por IP para reducir ataques de fuerza bruta y abuso.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,10');
Route::get('/configuracion-publica', [ConfiguracionController::class, 'publica'])->middleware('throttle:60,1');


// =====================================================
// 🔒 RUTAS PROTEGIDAS CON TOKEN
// =====================================================
Route::middleware([VerificarTokenSistema::class])->group(function () {

    // Sincronización de la aplicación Flutter (en línea / sin internet)
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);

    // =====================================================
    // 🔐 SESIÓN / PERFIL
    // =====================================================
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Foto de perfil del usuario autenticado
    Route::put('/perfil/foto', [AuthController::class, 'actualizarFotoPerfil']);
    Route::delete('/perfil/foto', [AuthController::class, 'eliminarFotoPerfil']);


    // =====================================================
    // ⚙️ CONFIGURACIÓN BÁSICA DEL NEGOCIO
    // =====================================================
    Route::get('/configuracion', [ConfiguracionController::class, 'index']);


    // =====================================================
    // 🔔 NOTIFICACIONES
    // =====================================================
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::put('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::put('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);


    // =====================================================
    // 👩‍💼 CONSULTAS BÁSICAS PARA USUARIOS AUTENTICADOS
    // =====================================================
    Route::get('/empleados/activos', [EmpleadoController::class, 'activos']);
    Route::get('/servicios', [ServicioController::class, 'index']);


    // =====================================================
    // 👑 RUTAS SOLO ADMINISTRADOR
    // =====================================================
    Route::middleware([VerificarRolSistema::class . ':admin'])->group(function () {

        // =====================================================
        // 📊 DASHBOARD
        // =====================================================
        Route::get('/dashboard', [CitaController::class, 'dashboard']);


        // =====================================================
        // ⚙️ CONFIGURACIÓN DEL NEGOCIO
        // =====================================================
        Route::put('/configuracion', [ConfiguracionController::class, 'update']);


        // =====================================================
        // 👤 CLIENTES
        // =====================================================
        Route::get('/clientes', [ClienteController::class, 'index']);
        Route::post('/clientes', [ClienteController::class, 'store']);
        Route::put('/clientes/{id}', [ClienteController::class, 'update']);
        Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);

        Route::get('/clientes/historial/buscar', [ClienteController::class, 'historial']);


        // =====================================================
        // 👩‍💼 EMPLEADOS / PERSONAL
        // =====================================================
        Route::get('/empleados', [EmpleadoController::class, 'index']);
        Route::get('/empleados/comisiones', [EmpleadoController::class, 'comisiones']);
        Route::post('/empleados', [EmpleadoController::class, 'store']);
        Route::put('/empleados/{id}', [EmpleadoController::class, 'update']);
        Route::delete('/empleados/{id}', [EmpleadoController::class, 'destroy']);


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
        // 🧾 CAJA DIARIA
        // =====================================================
        Route::get('/caja/diaria', [PagoController::class, 'cajaDiaria']);


        // =====================================================
        // 💅 SERVICIOS / COMBOS
        // =====================================================
        Route::post('/servicios/cargar-base', [ServicioController::class, 'cargarBase']);
        Route::post('/servicios', [ServicioController::class, 'store']);
        Route::put('/servicios/{id}', [ServicioController::class, 'update']);
        Route::delete('/servicios/{id}', [ServicioController::class, 'destroy']);


        // =====================================================
        // 🔔 ADMINISTRACIÓN DE NOTIFICACIONES
        // =====================================================
        Route::delete('/notificaciones/limpiar', [NotificacionController::class, 'limpiar']);


        // =====================================================
        // 🗑️ HISTORIAL / RECUPERACIÓN
        // =====================================================
        Route::get('/historial/eliminados', [HistorialController::class, 'index']);

        Route::put('/historial/clientes/{id}/restaurar', [HistorialController::class, 'restaurarCliente']);
        Route::put('/historial/citas/{id}/restaurar', [HistorialController::class, 'restaurarCita']);
        Route::put('/historial/pagos/{id}/restaurar', [HistorialController::class, 'restaurarPago']);

        Route::put('/historial/restaurar-todo', [HistorialController::class, 'restaurarTodo']);

        Route::delete('/historial/clientes/{id}/eliminar', [HistorialController::class, 'eliminarClienteDefinitivo']);
        Route::delete('/historial/citas/{id}/eliminar', [HistorialController::class, 'eliminarCitaDefinitivo']);
        Route::delete('/historial/pagos/{id}/eliminar', [HistorialController::class, 'eliminarPagoDefinitivo']);

        Route::delete('/historial/limpiar', [HistorialController::class, 'limpiarHistorial']);


        // =====================================================
        // 📄 REPORTES
        // =====================================================
        Route::get('/reportes/extracto-mensual', [ReporteController::class, 'extractoMensual']);
        Route::get('/reportes/caja-diaria', [ReporteController::class, 'cajaDiaria']);
        Route::get('/reportes/empleados', [ReporteController::class, 'empleados']);
    });


    // =====================================================
    // 👩‍💼 RUTAS SOLO EMPLEADO
    // =====================================================
    Route::middleware([VerificarRolSistema::class . ':empleado'])->group(function () {

        // El empleado solo ve sus propias citas asignadas
        Route::get('/empleado/mis-citas', [CitaController::class, 'misCitasEmpleado']);

        // El empleado solo puede finalizar sus propias citas
        Route::put('/empleado/mis-citas/{id}/finalizar', [CitaController::class, 'finalizarMiCitaEmpleado']);
    });
});
