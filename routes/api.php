<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashflowImportExportController;
use App\Http\Controllers\CashflowRecordController;
use App\Http\Controllers\CashflowSettingsController;
use App\Http\Controllers\ExposureBundleController;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\FlightImportExportController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\StockSettingsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockImportExportController;
use App\Http\Controllers\StockPriceHistoryController;
use App\Http\Controllers\StockTransactionController;
use Illuminate\Support\Facades\Route;

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::post('verify-password', [AuthController::class, 'verifyPassword']);
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
        Route::get('transactions', [StockTransactionController::class, 'index']);
        Route::post('transactions', [StockTransactionController::class, 'store']);
        Route::put('transactions/{stock_transaction}', [StockTransactionController::class, 'update']);
        Route::delete('transactions/{stock_transaction}', [StockTransactionController::class, 'destroy']);

        // Settings
        Route::get('settings', [StockSettingsController::class, 'show']);
        Route::patch('settings', [StockSettingsController::class, 'update']);

        // Import / Export
        Route::get('export', [StockImportExportController::class, 'export']);
        Route::post('import/preview', [StockImportExportController::class, 'preview']);
        Route::post('import', [StockImportExportController::class, 'import']);

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

    Route::get('export', [CashflowImportExportController::class, 'export']);
    Route::post('import/preview', [CashflowImportExportController::class, 'preview']);
    Route::post('import', [CashflowImportExportController::class, 'import']);

    Route::get('records', [CashflowRecordController::class, 'index']);
    Route::post('records/bulk', [CashflowRecordController::class, 'bulk']);
    Route::post('records', [CashflowRecordController::class, 'store']);
    Route::patch('records/{record}', [CashflowRecordController::class, 'update']);
    Route::delete('records/{record}', [CashflowRecordController::class, 'destroy']);
});

// Flights module
Route::prefix('flights')->middleware('auth:sanctum')->group(function () {
    Route::get('airports', [AirportController::class, 'index']);
    Route::get('airports/{iata}', [AirportController::class, 'show']);

    Route::get('stats', [FlightController::class, 'stats']);

    Route::get('export', [FlightImportExportController::class, 'export']);
    Route::post('import/preview', [FlightImportExportController::class, 'preview']);
    Route::post('import', [FlightImportExportController::class, 'import']);
    Route::post('import/fr24', [FlightController::class, 'importFr24']);
    Route::delete('import/fr24', [FlightController::class, 'deleteFr24Imports']);

    Route::post('lookup', [FlightController::class, 'lookup']);

    Route::get('settings', [FlightController::class, 'showSettings']);
    Route::patch('settings', [FlightController::class, 'updateSettings']);

    Route::get('/', [FlightController::class, 'index']);
    Route::post('/', [FlightController::class, 'store']);
    Route::patch('{flight}', [FlightController::class, 'update']);
    Route::delete('{flight}', [FlightController::class, 'destroy']);
    Route::post('{flight}/track', [FlightController::class, 'uploadTrack']);
    Route::delete('{flight}/track', [FlightController::class, 'deleteTrack']);
});
