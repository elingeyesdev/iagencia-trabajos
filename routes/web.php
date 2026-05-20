<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', function () {
    /** @var \App\Models\User $user */
    $user = \App\Models\User::find(auth()->id());

    if (!$user) {
        return redirect()->route('login');
    }

    if ($user->hasRole('administrador')) {
        return view('dashboard_3');
    } elseif ($user->hasRole('contratador')) {
        return view('dashboard_2');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';