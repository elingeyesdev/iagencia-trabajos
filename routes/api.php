<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioApiController;
use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\CategoriaApiController;
use App\Http\Controllers\Api\PublicacionApiController;
use App\Http\Controllers\Api\PostulanteApiController;
use App\Http\Controllers\Api\PostulacionApiController;
use App\Http\Controllers\Api\ContratacionApiController;
use App\Http\Controllers\Api\CalificacionApiController;

/*****************************************************
 * Rutas API
 *****************************************************/
Route::post('/login', [AuthController::class, 'login']);

// ── Autenticadas ──
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return $request->user()->load('roles');
    });

    // ── Perfil de Usuario ──
    Route::put('/perfil', [UsuarioApiController::class, 'actualizarPerfil']);

    // ── Categorías (Lectura permitida para cualquier usuario autenticado) ──
    Route::get('/categorias', [CategoriaApiController::class, 'index']);

    // ── Usuarios ──
    Route::get('/usuarios',          [UsuarioApiController::class, 'index']);
    Route::post('/usuarios',         [UsuarioApiController::class, 'store']);
    Route::get('/usuarios/{id}',     [UsuarioApiController::class, 'show']);
    Route::put('/usuarios/{id}',     [UsuarioApiController::class, 'update']);
    Route::delete('/usuarios/{id}',  [UsuarioApiController::class, 'destroy']);

    // ── Admin ──
    Route::middleware('role:administrador')->prefix('admin')->group(function () {
        Route::get('/dashboard',         [AdminApiController::class, 'dashboard']);
        Route::get('/usuarios',          [AdminApiController::class, 'usuarios']);
        Route::post('/usuarios',         [AdminApiController::class, 'storeUsuario']);
        Route::put('/usuarios/{id}',     [AdminApiController::class, 'updateUsuario']);
        Route::delete('/usuarios/{id}',  [AdminApiController::class, 'destroyUsuario']);
        Route::get('/roles',             [AdminApiController::class, 'roles']);
        Route::post('/asignar-rol',      [AdminApiController::class, 'asignarRol']);
        Route::post('/quitar-rol',       [AdminApiController::class, 'quitarRol']);
        Route::post('/roles',            [AdminApiController::class, 'storeRol']);
        Route::put('/roles/{id}',        [AdminApiController::class, 'updateRol']);
        Route::delete('/roles/{id}',     [AdminApiController::class, 'destroyRol']);

        // Categorías
        Route::get('/categorias',        [CategoriaApiController::class, 'index']);
        Route::post('/categorias',       [CategoriaApiController::class, 'store']);
        Route::put('/categorias/{id}',   [CategoriaApiController::class, 'update']);
        Route::delete('/categorias/{id}',[CategoriaApiController::class, 'destroy']);

        // Publicaciones
        Route::get('/publicaciones',         [PublicacionApiController::class, 'index']);
        Route::post('/publicaciones',        [PublicacionApiController::class, 'store']);
        Route::put('/publicaciones/{id}',    [PublicacionApiController::class, 'update']);
        Route::delete('/publicaciones/{id}', [PublicacionApiController::class, 'destroy']);

        // Postulantes
        Route::get('/postulantes',         [PostulanteApiController::class, 'index']);
        Route::post('/postulantes',        [PostulanteApiController::class, 'store']);
        Route::put('/postulantes/{id}',    [PostulanteApiController::class, 'update']);
        Route::delete('/postulantes/{id}', [PostulanteApiController::class, 'destroy']);

        // Oversight transaccional
        Route::get('/postulaciones',          [PostulacionApiController::class, 'index']);
        Route::delete('/postulaciones/{id}',  [PostulacionApiController::class, 'destroy']);
        Route::get('/contrataciones',         [ContratacionApiController::class, 'index']);
        Route::delete('/contrataciones/{id}', [ContratacionApiController::class, 'destroy']);
        Route::get('/calificaciones',         [CalificacionApiController::class, 'index']);
        Route::delete('/calificaciones/{id}', [CalificacionApiController::class, 'destroy']);
    });

    // ── Contratador ──
    Route::middleware('role:contratador')->prefix('contratador')->group(function () {
        Route::post('/publicaciones',        [PublicacionApiController::class, 'store']);
        Route::put('/publicaciones/{id}',   [PublicacionApiController::class, 'update']);
        Route::delete('/publicaciones/{id}',[PublicacionApiController::class, 'destroy']);
        
        Route::get('/postulaciones',             [PostulacionApiController::class, 'postulacionesRecibidas']);
        Route::put('/postulaciones/{id}/estado', [PostulacionApiController::class, 'actualizarEstado']);

        Route::get('/contrataciones',  [ContratacionApiController::class, 'misContrataciones']);
        Route::post('/contrataciones', [ContratacionApiController::class, 'store']);
        Route::put('/contrataciones/{id}/estado', [ContratacionApiController::class, 'actualizarEstado']);
        Route::post('/calificaciones', [CalificacionApiController::class, 'store']);
    });


    // ── Trabajador ──
    Route::middleware('role:trabajador')->prefix('trabajador')->group(function () {
        Route::get('/dashboard',                [PostulacionApiController::class, 'dashboardWorker']);
        Route::get('/postulaciones',            [PostulacionApiController::class, 'misPostulaciones']);
        Route::post('/postulaciones',           [PostulacionApiController::class, 'store']);
        Route::delete('/postulaciones/{id}',    [PostulacionApiController::class, 'cancelar']);
        Route::put('/perfil-postulante',        [PostulacionApiController::class, 'guardarPerfilPostulante']);

        Route::post('/calificaciones', [CalificacionApiController::class, 'store']);
    });
});