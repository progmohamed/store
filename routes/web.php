<?php

use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [StoreController::class, 'login'])->name('login');
Route::post('/login', [StoreController::class, 'login'])->middleware('throttle:5,1');
Route::middleware('store.auth')->group(function (): void {
    Route::get('/', [StoreController::class, 'index'])->name('dashboard');
    Route::post('/medicines', [StoreController::class, 'medicine'])->name('medicines.store');
    Route::post('/receipts', [StoreController::class, 'receive'])->name('receipts.store');
    Route::post('/logout', [StoreController::class, 'logout'])->name('logout');
});
