<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebauthnAuthController extends Controller
{
    public function options(WebauthnService $service): JsonResponse
    {
        $optionsJson = $service->buildRequestOptionsJson();
        $uuid        = Str::uuid()->toString();

        Cache::put("webauthn_auth_{$uuid}", $optionsJson, 300);

        $decoded             = json_decode($optionsJson);
        $decoded->session_id = $uuid;

        return response()->json($decoded);
    }

    public function authenticate(Request $request, WebauthnService $service): JsonResponse
    {
        $request->validate([
            'credential' => ['required', 'string'],
            'session_id' => ['required', 'string'],
        ]);

        $optionsJson = Cache::pull("webauthn_auth_{$request->input('session_id')}");
        abort_unless($optionsJson, 422, 'Authentication session expired. Please try again.');

        $credJson = $request->input('credential');
        $credData = json_decode($credJson, true);

        $rawId = $credData['rawId'] ?? $credData['id'] ?? null;
        abort_unless($rawId, 422, 'Missing credential ID.');

        $credentialId = base64_encode(base64_decode(strtr($rawId, '-_', '+/')));

        $stored = WebauthnCredential::where('credential_id', $credentialId)->first();
        abort_unless($stored, 401, 'Passkey not recognised.');

        $record = $service->deserializeRecord($stored->credential_data);

        $updatedRecord = $service->verifyAuthentication(
            $credJson,
            $optionsJson,
            $record,
            parse_url(config('webauthn.origin'), PHP_URL_HOST),
            $record->userHandle,
        );

        $stored->update([
            'credential_data' => $service->serializeRecord($updatedRecord),
            'last_used_at'    => now(),
        ]);

        $user  = User::findOrFail($record->userHandle);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }
}
