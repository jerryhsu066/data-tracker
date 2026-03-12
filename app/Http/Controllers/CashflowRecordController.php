<?php

namespace App\Http\Controllers;

use App\Models\CashflowRecord;
use App\Models\CashflowSubtype;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
        $userId          = $request->user()->id;
        $typeHasSubtypes = CashflowSubtype::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('type_id', $request->type_id)
            ->exists();

        $validated = $request->validate([
            'recorded_at' => ['required', 'date'],
            'type_id'     => [
                'required',
                Rule::exists('cashflow_types', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at'),
            ],
            'subtype_id'  => [
                $typeHasSubtypes ? 'required' : 'nullable',
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

        $userId          = $request->user()->id;
        $typeId          = $request->input('type_id', $record->type_id);
        $typeHasSubtypes = CashflowSubtype::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('type_id', $typeId)
            ->exists();

        $validated = $request->validate([
            'recorded_at' => ['sometimes', 'date'],
            'type_id'     => [
                'sometimes',
                Rule::exists('cashflow_types', 'id')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at'),
            ],
            'subtype_id'  => [
                $typeHasSubtypes ? 'required' : 'sometimes',
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

    public function bulk(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'year'             => ['required', 'integer'],
            'month'            => ['required', 'integer', 'min:1', 'max:12'],
            'creates'          => ['sometimes', 'array'],
            'creates.*.type_id'    => ['required', 'integer', Rule::exists('cashflow_types', 'id')->where('user_id', $userId)->whereNull('deleted_at')],
            'creates.*.subtype_id' => ['nullable', 'integer', Rule::exists('cashflow_subtypes', 'id')->where('user_id', $userId)->whereNull('deleted_at')],
            'creates.*.amount'     => ['required', 'numeric', 'min:0.01'],
            'creates.*.note'       => ['nullable', 'string', 'max:500'],
            'updates'          => ['sometimes', 'array'],
            'updates.*.id'         => ['required', 'integer'],
            'updates.*.amount'     => ['required', 'numeric', 'min:0.01'],
            'updates.*.note'       => ['nullable', 'string', 'max:500'],
            'deletes'          => ['sometimes', 'array'],
            'deletes.*'            => ['required', 'integer'],
        ]);

        $recordedAt        = sprintf('%04d-%02d-01', $validated['year'], $validated['month']);
        $typesWithSubtypes = array_flip(
            CashflowSubtype::where('user_id', $userId)->whereNull('deleted_at')->pluck('type_id')->unique()->all()
        );
        $created = [];

        DB::transaction(function () use ($validated, $userId, $recordedAt, $typesWithSubtypes, &$created) {
            if (!empty($validated['deletes'])) {
                CashflowRecord::whereIn('id', $validated['deletes'])
                    ->where('user_id', $userId)
                    ->delete();
            }

            foreach ($validated['updates'] ?? [] as $u) {
                CashflowRecord::where('id', $u['id'])
                    ->where('user_id', $userId)
                    ->update(['amount' => $u['amount'], 'note' => $u['note'] ?? null]);
            }

            foreach ($validated['creates'] ?? [] as $c) {
                if (isset($typesWithSubtypes[$c['type_id']]) && empty($c['subtype_id'])) {
                    abort(422, 'subtype_id is required for this type');
                }
                $created[] = CashflowRecord::create([
                    'user_id'     => $userId,
                    'recorded_at' => $recordedAt,
                    'type_id'     => $c['type_id'],
                    'subtype_id'  => $c['subtype_id'] ?? null,
                    'amount'      => $c['amount'],
                    'note'        => $c['note'] ?? null,
                ]);
            }
        });

        return response()->json(['created' => $created]);
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
