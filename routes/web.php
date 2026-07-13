<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\InstanceController;
use Illuminate\Support\Facades\Route;

// La raíz redirige al panel.
Route::redirect('/', '/admin');

/*
|--------------------------------------------------------------------------
| Panel administrativo (para CIMCO)
|--------------------------------------------------------------------------
*/

// Login del admin.
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Gestión de instancias (requiere sesión de admin).
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [InstanceController::class, 'index'])->name('instances.index');
    Route::get('/instancias/crear', [InstanceController::class, 'create'])->name('instances.create');
    Route::post('/instancias', [InstanceController::class, 'store'])->name('instances.store');
    Route::get('/instancias/{tenant}', [InstanceController::class, 'show'])->name('instances.show');
    Route::put('/instancias/{tenant}/proveedor', [InstanceController::class, 'updateProvider'])->name('instances.provider');
    Route::put('/instancias/{tenant}/whatsapp', [InstanceController::class, 'updateWhatsapp'])->name('instances.whatsapp');
    Route::put('/instancias/{tenant}/twilio', [InstanceController::class, 'updateTwilio'])->name('instances.twilio');
    Route::post('/instancias/{tenant}/regenerar-secret', [InstanceController::class, 'regenerateSecret'])->name('instances.regenerate');
    Route::post('/instancias/{tenant}/estado', [InstanceController::class, 'toggleActive'])->name('instances.toggle');
    Route::post('/instancias/{tenant}/probar', [InstanceController::class, 'sendTest'])->name('instances.test');
});
