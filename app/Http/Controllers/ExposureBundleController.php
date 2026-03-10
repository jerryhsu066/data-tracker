<?php

namespace App\Http\Controllers;

use App\Models\ExposureBundle;
use App\Models\ExposureBundleEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExposureBundleController extends Controller
{
    private function bundleResource(ExposureBundle $bundle, int $userId): array
    {
        $bundle->loadMissing(['entries' => fn ($q) => $q->with('stock')]);

        return [
            'id'      => $bundle->id,
            'name'    => $bundle->name,
            'cash'    => (int) $bundle->cash,
            'entries' => $bundle->entries->map(fn (ExposureBundleEntry $entry) => [
                'id'              => $entry->id,
                'stock'           => $entry->stock,
                'leverage'        => $entry->leverage,
                'is_cash'         => $entry->is_cash,
                'shares_override' => $entry->shares_override,
                'net_shares'      => number_format(
                    $entry->shares_override !== null
                        ? (float) $entry->shares_override
                        : $entry->stock->netSharesForUser($userId),
                    4, '.', ''
                ),
            ])->values()->all(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $bundles = ExposureBundle::where('user_id', $userId)
            ->with(['entries.stock'])
            ->get();

        $result = $bundles->map(fn (ExposureBundle $bundle) => $this->bundleResource($bundle, $userId));

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $bundle = ExposureBundle::create([
            'user_id' => $request->user()->id,
            'name'    => $validated['name'],
            'cash'    => 0,
        ]);

        return response()->json($this->bundleResource($bundle, $request->user()->id), 201);
    }

    public function update(Request $request, ExposureBundle $bundle): JsonResponse
    {
        if ($bundle->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'cash' => ['sometimes', 'integer', 'min:0'],
        ]);

        $bundle->update($validated);

        return response()->json($this->bundleResource($bundle->fresh(), $request->user()->id));
    }

    public function destroy(Request $request, ExposureBundle $bundle): Response
    {
        if ($bundle->user_id !== $request->user()->id) {
            abort(403);
        }

        $bundle->delete();

        return response()->noContent();
    }

    public function addEntry(Request $request, ExposureBundle $bundle): JsonResponse
    {
        if ($bundle->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'stock_id'        => ['required', 'integer', 'exists:stocks,id'],
            'shares_override' => ['nullable', 'numeric', 'gte:0'],
            'leverage'        => ['required', 'numeric', 'min:0'],
            'is_cash'         => ['required', 'boolean'],
        ]);

        ExposureBundleEntry::create([
            'bundle_id'       => $bundle->id,
            'stock_id'        => $validated['stock_id'],
            'shares_override' => $validated['shares_override'] ?? null,
            'leverage'        => $validated['leverage'],
            'is_cash'         => $validated['is_cash'],
        ]);

        $bundle->refresh();

        return response()->json($this->bundleResource($bundle, $request->user()->id));
    }

    public function updateEntry(Request $request, ExposureBundle $bundle, ExposureBundleEntry $entry): JsonResponse
    {
        if ($bundle->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'shares_override' => ['nullable', 'numeric', 'gte:0'],
            'leverage'        => ['sometimes', 'numeric', 'min:0'],
            'is_cash'         => ['sometimes', 'boolean'],
        ]);

        $entry->update($validated);
        $bundle->refresh();

        return response()->json($this->bundleResource($bundle, $request->user()->id));
    }

    public function removeEntry(Request $request, ExposureBundle $bundle, ExposureBundleEntry $entry): Response
    {
        if ($bundle->user_id !== $request->user()->id) {
            abort(403);
        }

        $entry->delete();

        return response()->noContent();
    }
}
