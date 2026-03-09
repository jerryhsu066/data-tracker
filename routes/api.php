<?php

use App\Http\Controllers\HoldingController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::apiResource('stocks', StockController::class)->parameters(['stocks' => 'symbol'])->only([
    'index', 'store', 'show', 'destroy',
]);
Route::post('stocks/{symbol}/fetch', [StockController::class, 'fetch']);

Route::apiResource('holdings', HoldingController::class);
