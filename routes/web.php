<?php

use App\Http\Controllers\Auth\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = \App\Models\User::find(auth()->id());
    if (!$user) return redirect()->route('login');

    if ($user->hasRole('administrador')) {
        // Crear token para el dashboard
        $token = $user->createToken('dashboard-admin')->plainTextToken;
        return view('admin.dashboard', compact('token'));
    } elseif ($user->hasRole('contratador')) {
        return view('dashboardContratador');
    }
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::get('/admin/panel', function () {
    $tokenParam = request()->bearerToken();
    if (!$tokenParam) return redirect()->route('login');

    $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenParam);
    if (!$tokenModel) return response('Token inválido', 401);

    $user = $tokenModel->tokenable;
    if (!$user->hasRole('administrador')) return response('No autorizado', 403);

    $token = $tokenParam;
    return view('admin.dashboard', compact('token'));
})->name('admin.panel');

// ── Reportes imprimibles (Incremento 4, solo administrador) ──
// Las vistas solo presentan: los datos los sirve la API (GET /api/admin/reportes/{tipo},
// ReporteApiController). Aquí solo se entrega la vista y su token de corta duración.
Route::middleware(['auth', 'verified', 'role:administrador'])
    ->prefix('reportes')
    ->name('reportes.')
    ->group(function () {
        Route::view('/', 'reportes.index')->name('index');

        foreach (\App\Http\Controllers\Api\ReporteApiController::VISTAS_URI as $uri => $tipo) {
            Route::get('/' . $uri, fn (\Illuminate\Http\Request $request) => view('reportes.' . $tipo, [
                'tipo'  => $tipo,
                'token' => \App\Http\Controllers\Api\ReporteApiController::tokenParaVista($request->user()),
            ]))->name($tipo);
        }
    });

// ── Business Intelligence: Dashboard de KPIs (Incremento 5, solo administrador) ──
// La vista solo presenta: los datos los sirve GET /api/admin/kpis/indicadores (KpiApiController).
// Token Sanctum de corta duración para que la vista consuma la API.
Route::get('/admin/kpis', fn (\Illuminate\Http\Request $request) => view('admin.kpis', [
    'token' => $request->user()->createToken('dashboard-kpis', ['*'], now()->addMinutes(120))->plainTextToken,
]))->middleware(['auth', 'verified', 'role:administrador'])->name('admin.kpis');

require __DIR__.'/auth.php';