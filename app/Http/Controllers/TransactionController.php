<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Transaction;
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
            $netShares = $stock->netShares();

            if ($validated['shares'] > $netShares) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'shares' => ["Cannot sell {$validated['shares']} shares — only {$netShares} owned."],
                    ],
                ], 422);
            }
        }

        $transaction = Transaction::create($validated);
        $transaction->load('stock');

        return response()->json($transaction, 201);
    }

    public function destroy(Transaction $transaction): Response
    {
        $transaction->delete();

        return response()->noContent();
    }
}
