<?php

use App\Http\Controllers\ProfileController;
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
require __DIR__.'/auth.php';