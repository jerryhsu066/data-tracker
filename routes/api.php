<?php

use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockPriceHistoryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('stocks', StockController::class)
    ->parameters(['stocks' => 'symbol'])
    ->only(['index', 'store', 'show', 'destroy']);

Route::get('stocks/{symbol}/transactions', [StockController::class, 'transactions']);
Route::post('stocks/{symbol}/fetch', [StockController::class, 'fetch']);
Route::get('stocks/{symbol}/prices', [StockPriceHistoryController::class, 'index']);

Route::post('transactions', [TransactionController::class, 'store']);
Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);

Route::get('portfolio', [PortfolioController::class, 'index']);
