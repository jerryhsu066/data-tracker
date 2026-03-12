<?php

namespace App\Http\Controllers;

use App\Jobs\FetchHistoricalPrices;
use App\Models\Stock;
use App\Models\StockTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockTransactionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stock_id'        => ['required', 'integer', 'exists:stocks,id'],
            'type'            => ['required', Rule::in(['buy', 'sell'])],
            'shares'          => ['required', 'numeric', 'gt:0'],
            'price_per_share' => ['required', 'numeric', 'gt:0'],
            'handling_fee'    => ['nullable', 'numeric', 'gte:0'],
            'transaction_tax' => ['nullable', 'numeric', 'gte:0'],
            'transacted_at'   => ['required', 'date'],
            'notes'           => ['nullable', 'string'],
        ]);

        if ($validated['type'] === 'sell') {
            $stock = Stock::find($validated['stock_id']);
            $netShares = $stock->netSharesForUser($request->user()->id);

            if ($validated['shares'] > $netShares) {
                throw ValidationException::withMessages([
                    'shares' => ["Cannot sell {$validated['shares']} shares — only {$netShares} owned."],
                ]);
            }
        }

        $tradeValue = $validated['shares'] * $validated['price_per_share'];
        $discount = (float) ($request->user()->handling_fee_discount ?? 0);

        $handlingFee = isset($validated['handling_fee'])
            ? (int) $validated['handling_fee']
            : (int) max(20, floor($tradeValue * 0.001425 * (1 - $discount)));

        $transactionTax = isset($validated['transaction_tax'])
            ? (int) $validated['transaction_tax']
            : ($validated['type'] === 'sell' ? (int) floor($tradeValue * 0.003) : 0);

        $transaction = StockTransaction::create([
            ...$validated,
            'user_id'         => $request->user()->id,
            'handling_fee'    => $handlingFee,
            'transaction_tax' => $transactionTax,
        ]);

        $transaction->load('stock');

        $today = Carbon::today('Asia/Taipei')->toDateString();
        $transactedAt = Carbon::parse($validated['transacted_at'])->toDateString();
        if ($transactedAt !== $today) {
            FetchHistoricalPrices::dispatch($transaction->stock, $transactedAt);
        }

        return response()->json($transaction, 201);
    }

    public function update(Request $request, StockTransaction $stockTransaction): JsonResponse
    {
        Gate::authorize('update', $stockTransaction);

        $validated = $request->validate([
            'type'             => ['required', Rule::in(['buy', 'sell'])],
            'shares'           => ['required', 'numeric', 'gt:0'],
            'price_per_share'  => ['required', 'numeric', 'gt:0'],
            'handling_fee'     => ['required', 'numeric', 'gte:0'],
            'transaction_tax'  => ['required', 'numeric', 'gte:0'],
            'transacted_at'    => ['required', 'date'],
            'notes'            => ['nullable', 'string'],
        ]);

        $stockTransaction->update($validated);
        $stockTransaction->load('stock');

        $today = Carbon::today('Asia/Taipei')->toDateString();
        $transactedAt = Carbon::parse($validated['transacted_at'])->toDateString();
        if ($transactedAt !== $today) {
            FetchHistoricalPrices::dispatch($stockTransaction->stock, $transactedAt);
        }

        return response()->json($stockTransaction);
    }

    public function destroy(StockTransaction $stockTransaction): Response
    {
        Gate::authorize('delete', $stockTransaction);

        $stockTransaction->delete();

        return response()->noContent();
    }
}
