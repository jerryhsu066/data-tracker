<?php

namespace App\Http\Controllers;

use App\Models\CashflowBank;
use App\Models\CashflowCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CashflowSettingsController extends Controller
{
    // ── Companies ─────────────────────────────────────────────────────────────

    public function listCompanies(Request $request): JsonResponse
    {
        return response()->json(
            CashflowCompany::where('user_id', $request->user()->id)->orderBy('name')->get()
        );
    }

    public function createCompany(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $company = CashflowCompany::create([
            'user_id' => $request->user()->id,
            'name'    => $validated['name'],
        ]);

        return response()->json($company, 201);
    }

    public function updateCompany(Request $request, CashflowCompany $company): JsonResponse
    {
        if ($company->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $company->update($validated);

        return response()->json($company);
    }

    public function deleteCompany(Request $request, CashflowCompany $company): Response
    {
        if ($company->user_id !== $request->user()->id) {
            abort(403);
        }

        $company->delete();

        return response()->noContent();
    }

    // ── Banks ─────────────────────────────────────────────────────────────────

    public function listBanks(Request $request): JsonResponse
    {
        return response()->json(
            CashflowBank::where('user_id', $request->user()->id)->orderBy('name')->get()
        );
    }

    public function createBank(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $bank = CashflowBank::create([
            'user_id' => $request->user()->id,
            'name'    => $validated['name'],
        ]);

        return response()->json($bank, 201);
    }

    public function updateBank(Request $request, CashflowBank $bank): JsonResponse
    {
        if ($bank->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $bank->update($validated);

        return response()->json($bank);
    }

    public function deleteBank(Request $request, CashflowBank $bank): Response
    {
        if ($bank->user_id !== $request->user()->id) {
            abort(403);
        }

        $bank->delete();

        return response()->noContent();
    }
}
