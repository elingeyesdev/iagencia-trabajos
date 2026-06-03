<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioApiController;
use App\Http\Controllers\Api\AdminApiController;

/*****************************************************
 * Rutas API
 *****************************************************/
Route::post('/login',  [AuthController::class, 'login']);

// ── Autenticadas ──
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return $request->user()->load('roles');
    });

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
        Route::post('/roles',        [AdminApiController::class, 'storeRol']);
        Route::put('/roles/{id}',    [AdminApiController::class, 'updateRol']);
        Route::delete('/roles/{id}', [AdminApiController::class, 'destroyRol']);
    });
});
