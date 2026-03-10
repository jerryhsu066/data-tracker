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

// Public stock reads
Route::get('stocks', [StockController::class, 'index']);
Route::get('stocks/{symbol}', [StockController::class, 'show']);
Route::get('stocks/{symbol}/prices', [StockPriceHistoryController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Stock write operations
    Route::post('stocks', [StockController::class, 'store']);
    Route::delete('stocks/{symbol}', [StockController::class, 'destroy']);
    Route::post('stocks/{symbol}/fetch', [StockController::class, 'fetch']);
    Route::get('stocks/{symbol}/transactions', [StockController::class, 'transactions']);

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
    Route::delete('exposure/bundles/{bundle}/entries/{entry}', [ExposureBundleController::class, 'removeEntry']);
});
