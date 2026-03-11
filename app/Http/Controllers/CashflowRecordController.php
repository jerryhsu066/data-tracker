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

        $query = CashflowRecord::where('user_id', $request->user()->id)
            ->whereYear('recorded_at', $request->year);

        if ($request->filled('month')) {
            $query->whereMonth('recorded_at', $request->month);
        }

        return response()->json($query->orderBy('recorded_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'recorded_at' => ['required', 'date'],
            'type_id'     => [
                'required',
                Rule::exists('cashflow_types', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at'),
            ],
            'subtype_id'  => [
                'nullable',
                Rule::exists('cashflow_subtypes', 'id')
                    ->where('type_id', $request->type_id)
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at'),
            ],
            'amount'      => ['required', 'numeric', 'min:0'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $record = CashflowRecord::create(['user_id' => $userId, ...$validated]);

        return response()->json($record->fresh(), 201);
    }

    public function update(Request $request, CashflowRecord $record): JsonResponse
    {
        if ($record->user_id !== $request->user()->id) {
            abort(403);
        }

        $userId     = $request->user()->id;
        $typeId     = $request->input('type_id', $record->type_id);

        $validated = $request->validate([
            'recorded_at' => ['sometimes', 'date'],
            'type_id'     => [
                'sometimes',
                Rule::exists('cashflow_types', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at'),
            ],
            'subtype_id'  => [
                'sometimes',
                'nullable',
                Rule::exists('cashflow_subtypes', 'id')
                    ->where('type_id', $typeId)
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at'),
            ],
            'amount'      => ['sometimes', 'numeric', 'min:0'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $record->update($validated);

        return response()->json($record->fresh());
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
