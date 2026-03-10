<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExposureBundleController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockPriceHistoryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Stocks
Route::prefix('stocks')->group(function () {
    // Public reads
    Route::get('/', [StockController::class, 'index']);
    Route::post('sync-history', [StockController::class, 'syncHistory'])->middleware('auth:sanctum');
    Route::get('{symbol}', [StockController::class, 'show']);
    Route::get('{symbol}/prices', [StockPriceHistoryController::class, 'index']);

    // Protected writes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [StockController::class, 'store']);
        Route::delete('{symbol}', [StockController::class, 'destroy']);
        Route::post('{symbol}/fetch', [StockController::class, 'fetch']);
        Route::get('{symbol}/transactions', [StockController::class, 'transactions']);
    });
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Transactions
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::put('transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);

    // Portfolio
    Route::get('portfolio', [PortfolioController::class, 'index']);
    Route::get('portfolio/history', [PortfolioController::class, 'history']);

    // Settings
    Route::get('settings', [SettingsController::class, 'show']);
    Route::patch('settings', [SettingsController::class, 'update']);

    // Exposure Bundles
    Route::get('exposure/bundles', [ExposureBundleController::class, 'index']);
    Route::post('exposure/bundles', [ExposureBundleController::class, 'store']);
    Route::patch('exposure/bundles/{bundle}', [ExposureBundleController::class, 'update']);
    Route::delete('exposure/bundles/{bundle}', [ExposureBundleController::class, 'destroy']);
    Route::post('exposure/bundles/{bundle}/entries', [ExposureBundleController::class, 'addEntry']);
    Route::patch('exposure/bundles/{bundle}/entries/{entry}', [ExposureBundleController::class, 'updateEntry']);
    Route::delete('exposure/bundles/{bundle}/entries/{entry}', [ExposureBundleController::class, 'removeEntry']);
});
