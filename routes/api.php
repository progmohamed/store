<?php

use App\Http\Controllers\StockApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['store.client', 'throttle:120,1'])->group(function (): void {
    Route::get('medicines', [StockApiController::class, 'index']);
    Route::post('quotes', [StockApiController::class, 'quote']);
    Route::post('issues', [StockApiController::class, 'store']);
    Route::post('issues/{requestId}/return', [StockApiController::class, 'returns'])->whereUuid('requestId');
});
