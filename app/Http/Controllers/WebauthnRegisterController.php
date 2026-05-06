<?php

namespace App\Http\Controllers;

use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebauthnRegisterController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        try {
            /** @var WebauthnService $service */
            $service     = app(WebauthnService::class);
            $user        = $request->user();
            $optionsJson = $service->buildCreationOptionsJson($user);

            Cache::put("webauthn_reg_{$user->id}", $optionsJson, 300);

            return response()->json(json_decode($optionsJson));
        } catch (\Throwable $e) {
            Log::error('WebAuthn options failed', [
                'user_id' => $request->user()?->id,
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Options failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => ['required', 'string'],
            'name'       => ['sometimes', 'string', 'max:255'],
        ]);

        try {
            /** @var WebauthnService $service */
            $service     = app(WebauthnService::class);
            $user        = $request->user();
            $optionsJson = Cache::pull("webauthn_reg_{$user->id}");

            if (! $optionsJson) {
                return response()->json(['message' => 'Registration session expired. Please try again.'], 422);
            }

            $record = $service->verifyRegistration(
                $request->input('credential'),
                $optionsJson,
                config('webauthn.rp_id'),
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
        } catch (\Throwable $e) {
            Log::error('WebAuthn registration failed', [
                'user_id' => $request->user()?->id,
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 422);
        }
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
