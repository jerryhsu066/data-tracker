<?php

namespace App\Http\Controllers;

use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class WebauthnRegisterController extends Controller
{
    public function options(Request $request, WebauthnService $service): JsonResponse
    {
        $user        = $request->user();
        $optionsJson = $service->buildCreationOptionsJson($user);

        Cache::put("webauthn_reg_{$user->id}", $optionsJson, 300);

        return response()->json(json_decode($optionsJson));
    }

    public function register(Request $request, WebauthnService $service): JsonResponse
    {
        $request->validate([
            'credential' => ['required', 'string'],
            'name'       => ['sometimes', 'string', 'max:255'],
        ]);

        $user        = $request->user();
        $optionsJson = Cache::pull("webauthn_reg_{$user->id}");

        abort_unless($optionsJson, 422, 'Registration session expired. Please try again.');

        $record = $service->verifyRegistration(
            $request->input('credential'),
            $optionsJson,
            parse_url(config('webauthn.origin'), PHP_URL_HOST)
        );

        $credential = WebauthnCredential::create([
            'user_id'         => $user->id,
            'name'            => $request->input('name', 'Passkey'),
            'credential_id'   => base64_encode($record->publicKeyCredentialId),
            'credential_data' => $service->serializeRecord($record),
        ]);

        return response()->json([
            'id'   => $credential->id,
            'name' => $credential->name,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $credentials = WebauthnCredential::where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get(['id', 'name', 'last_used_at', 'created_at']);

        return response()->json($credentials);
    }

    public function destroy(Request $request, WebauthnCredential $credential): Response
    {
        abort_unless($credential->user_id === $request->user()->id, 403);

        $credential->delete();

        return response()->noContent();
    }
}
