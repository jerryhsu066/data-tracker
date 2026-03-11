<?php

namespace App\Http\Controllers;

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
        $types = CashflowType::with('subtypes')
            ->where('user_id', $request->user()->id)
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

        return response()->json($type->load('subtypes'));
    }

    public function deleteType(Request $request, CashflowType $type): Response
    {
        if ($type->user_id !== $request->user()->id) {
            abort(403);
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
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $subtype = CashflowSubtype::create([
            'type_id'    => $type->id,
            'user_id'    => $request->user()->id,
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($subtype, 201);
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

        $subtype->delete();

        return response()->noContent();
    }
}
