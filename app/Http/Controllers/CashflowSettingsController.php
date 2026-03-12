<?php

namespace App\Http\Controllers;

use App\Models\CashflowRecord;
use App\Models\CashflowSubtype;
use App\Models\CashflowType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CashflowSettingsController extends Controller
{
    // ── Types ─────────────────────────────────────────────────────────────────

    public function listTypes(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $types = CashflowType::with('subtypes')
            ->withCount(['records as unsubtyped_records_count' => function ($q) {
                $q->whereNull('cashflow_subtype_id');
            }])
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json($types);
    }

    public function createType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'is_expense' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $type = CashflowType::create([
            'user_id'    => $request->user()->id,
            'name'       => $validated['name'],
            'is_expense' => $validated['is_expense'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($type->load('subtypes'), 201);
    }

    public function updateType(Request $request, CashflowType $type): JsonResponse
    {
        if ($type->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'is_expense'     => ['sometimes', 'boolean'],
            'sort_order'     => ['sometimes', 'integer', 'min:0'],
            'is_disabled'    => ['sometimes', 'boolean'],
            'is_private'     => ['sometimes', 'boolean'],
            'merge_subtypes' => ['sometimes', 'boolean'],
        ]);

        $type->update($validated);

        // Cascade is_disabled and is_private down to all subtypes
        $cascade = array_intersect_key($validated, array_flip(['is_disabled', 'is_private']));
        if (!empty($cascade)) {
            CashflowSubtype::where('cashflow_type_id', $type->id)->whereNull('deleted_at')->update($cascade);
        }

        return response()->json($type->fresh()->load('subtypes'));
    }

    public function deleteType(Request $request, CashflowType $type): Response
    {
        if ($type->user_id !== $request->user()->id) {
            abort(403);
        }

        if (CashflowRecord::where('cashflow_type_id', $type->id)->exists()) {
            abort(422, 'This type has existing records. Disable it instead.');
        }

        $type->delete();

        return response()->noContent();
    }

    // ── Subtypes ──────────────────────────────────────────────────────────────

    public function createSubtype(Request $request, CashflowType $type): JsonResponse
    {
        if ($type->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'sort_order'       => ['sometimes', 'integer', 'min:0'],
            'migrate_existing' => ['sometimes', 'boolean'],
        ]);

        $isFirstSubtype = !CashflowSubtype::where('cashflow_type_id', $type->id)->whereNull('deleted_at')->exists();

        $subtype = CashflowSubtype::create([
            'cashflow_type_id' => $type->id,
            'user_id'          => $request->user()->id,
            'name'             => $validated['name'],
            'sort_order'       => $validated['sort_order'] ?? 0,
        ]);

        $migratedCount = 0;
        if ($isFirstSubtype && ($validated['migrate_existing'] ?? false)) {
            $migratedCount = CashflowRecord::where('cashflow_type_id', $type->id)
                ->whereNull('cashflow_subtype_id')
                ->whereNull('deleted_at')
                ->update(['cashflow_subtype_id' => $subtype->id]);
        }

        return response()->json(['subtype' => $subtype, 'migrated_count' => $migratedCount], 201);
    }

    public function updateSubtype(Request $request, CashflowSubtype $subtype): JsonResponse
    {
        if ($subtype->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_disabled' => ['sometimes', 'boolean'],
            'is_private'  => ['sometimes', 'boolean'],
        ]);

        $subtype->update($validated);

        return response()->json($subtype);
    }

    public function deleteSubtype(Request $request, CashflowSubtype $subtype): Response
    {
        if ($subtype->user_id !== $request->user()->id) {
            abort(403);
        }

        if (CashflowRecord::where('cashflow_subtype_id', $subtype->id)->exists()) {
            abort(422, 'This subtype has existing records. Disable it instead.');
        }

        $subtype->delete();

        return response()->noContent();
    }
}
