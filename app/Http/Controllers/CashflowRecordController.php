<?php

namespace App\Http\Controllers;

use App\Models\CashflowRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CashflowRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'year'  => ['required', 'integer'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        $query = CashflowRecord::with(['company', 'bank'])
            ->where('user_id', $request->user()->id)
            ->whereYear('recorded_at', $request->year);

        if ($request->filled('month')) {
            $query->whereMonth('recorded_at', $request->month);
        }

        $records = $query->orderBy('recorded_at')->get();

        return response()->json($records);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'recorded_at' => ['required', 'date'],
            'type'        => ['required', Rule::in(['income', 'rent', 'credit_card', 'other'])],
            'amount'      => ['required', 'numeric', 'min:0'],
            'company_id'  => [
                Rule::requiredIf($request->type === 'income'),
                'nullable',
                Rule::exists('cashflow_companies', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'bank_id'     => [
                Rule::requiredIf($request->type === 'credit_card'),
                'nullable',
                Rule::exists('cashflow_banks', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $record = CashflowRecord::create(['user_id' => $userId, ...$validated]);

        return response()->json($record->fresh()->load(['company', 'bank']), 201);
    }

    public function update(Request $request, CashflowRecord $record): JsonResponse
    {
        if ($record->user_id !== $request->user()->id) {
            abort(403);
        }

        $userId = $request->user()->id;
        $type   = $request->input('type', $record->type);

        $validated = $request->validate([
            'recorded_at' => ['sometimes', 'date'],
            'type'        => ['sometimes', Rule::in(['income', 'rent', 'credit_card', 'other'])],
            'amount'      => ['sometimes', 'numeric', 'min:0'],
            'company_id'  => [
                Rule::requiredIf($type === 'income' && $request->has('type')),
                'nullable',
                Rule::exists('cashflow_companies', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'bank_id'     => [
                Rule::requiredIf($type === 'credit_card' && $request->has('type')),
                'nullable',
                Rule::exists('cashflow_banks', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $record->update($validated);

        return response()->json($record->load(['company', 'bank']));
    }

    public function destroy(Request $request, CashflowRecord $record): Response
    {
        if ($record->user_id !== $request->user()->id) {
            abort(403);
        }

        $record->delete();

        return response()->noContent();
    }
}
