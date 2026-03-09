<?php

namespace App\Http\Controllers;

use App\Jobs\FetchHistoricalPrices;
use App\Models\Stock;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stock_id' => ['required', 'integer', 'exists:stocks,id'],
            'type' => ['required', Rule::in(['buy', 'sell'])],
            'shares' => ['required', 'numeric', 'gt:0'],
            'price_per_share' => ['required', 'numeric', 'gt:0'],
            'transacted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validated['type'] === 'sell') {
            $stock = Stock::find($validated['stock_id']);
            $netShares = $stock->netSharesForUser($request->user()->id);

            if ($validated['shares'] > $netShares) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'shares' => ["Cannot sell {$validated['shares']} shares — only {$netShares} owned."],
                    ],
                ], 422);
            }
        }

        $tradeValue = $validated['shares'] * $validated['price_per_share'];
        $discount = (float) ($request->user()->handling_fee_discount ?? 0);
        $handlingFee = (int) max(20, floor($tradeValue * 0.001425 * (1 - $discount)));
        $transactionTax = $validated['type'] === 'sell'
            ? (int) floor($tradeValue * 0.003)
            : 0;

        $transaction = Transaction::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'handling_fee' => $handlingFee,
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

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'type'             => ['required', Rule::in(['buy', 'sell'])],
            'shares'           => ['required', 'numeric', 'gt:0'],
            'price_per_share'  => ['required', 'numeric', 'gt:0'],
            'handling_fee'     => ['required', 'numeric', 'gte:0'],
            'transaction_tax'  => ['required', 'numeric', 'gte:0'],
            'transacted_at'    => ['required', 'date'],
            'notes'            => ['nullable', 'string'],
        ]);

        $transaction->update($validated);
        $transaction->load('stock');

        $today = Carbon::today('Asia/Taipei')->toDateString();
        $transactedAt = Carbon::parse($validated['transacted_at'])->toDateString();
        if ($transactedAt !== $today) {
            FetchHistoricalPrices::dispatch($transaction->stock, $transactedAt);
        }

        return response()->json($transaction);
    }

    public function destroy(Request $request, Transaction $transaction): Response
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->delete();

        return response()->noContent();
    }
}
