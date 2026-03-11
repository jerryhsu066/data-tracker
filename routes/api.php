<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashflowRecordController;
use App\Http\Controllers\CashflowSettingsController;
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

// Stocks module — all routes share the /stocks prefix.
// Static paths must be declared before the {symbol} wildcard to take priority.
Route::prefix('stocks')->group(function () {

    // Public
    Route::get('/', [StockController::class, 'index']);

    // Protected — static paths
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [StockController::class, 'store']);
        Route::post('sync-history', [StockController::class, 'syncHistory']);

        // Portfolio
        Route::get('portfolio', [PortfolioController::class, 'index']);
        Route::get('portfolio/history', [PortfolioController::class, 'history']);

        // Transactions
        Route::post('transactions', [TransactionController::class, 'store']);
        Route::put('transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);

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

    // Public — dynamic {symbol} paths (must come after all static paths)
    Route::get('{symbol}', [StockController::class, 'show']);
    Route::get('{symbol}/prices', [StockPriceHistoryController::class, 'index']);

    // Protected — dynamic {symbol} paths
    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('{symbol}', [StockController::class, 'destroy']);
        Route::post('{symbol}/fetch', [StockController::class, 'fetch']);
        Route::get('{symbol}/transactions', [StockController::class, 'transactions']);
    });
});

// Cashflow module
Route::prefix('cashflow')->middleware('auth:sanctum')->group(function () {
    Route::prefix('settings')->group(function () {
        Route::get('types', [CashflowSettingsController::class, 'listTypes']);
        Route::post('types', [CashflowSettingsController::class, 'createType']);
        Route::patch('types/{type}', [CashflowSettingsController::class, 'updateType']);
        Route::delete('types/{type}', [CashflowSettingsController::class, 'deleteType']);
        Route::post('types/{type}/subtypes', [CashflowSettingsController::class, 'createSubtype']);
        Route::patch('subtypes/{subtype}', [CashflowSettingsController::class, 'updateSubtype']);
        Route::delete('subtypes/{subtype}', [CashflowSettingsController::class, 'deleteSubtype']);
    });

    Route::get('records', [CashflowRecordController::class, 'index']);
    Route::post('records', [CashflowRecordController::class, 'store']);
    Route::patch('records/{record}', [CashflowRecordController::class, 'update']);
    Route::delete('records/{record}', [CashflowRecordController::class, 'destroy']);
});
