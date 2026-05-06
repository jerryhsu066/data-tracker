<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Webauthn\CredentialRecord;

class WebauthnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Registration options ──────────────────────────────────────────────────

    public function test_registration_options_returns_challenge_and_rp(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/auth/webauthn/register/options')
            ->assertOk()
            ->assertJsonStructure(['challenge', 'rp', 'user', 'pubKeyCredParams'])
            ->assertJsonPath('rp.id', config('webauthn.rp_id'))
            ->assertJsonPath('user.name', $this->user->email);
    }

    public function test_registration_options_caches_options(): void
    {
        $this->actingAs($this->user)->postJson('/api/auth/webauthn/register/options');

        $this->assertTrue(Cache::has("webauthn_reg_{$this->user->id}"));
    }

    public function test_registration_options_requires_auth(): void
    {
        $this->postJson('/api/auth/webauthn/register/options')->assertUnauthorized();
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function test_register_stores_credential_on_success(): void
    {
        $fakeRecord = $this->makeFakeCredentialRecord();

        $this->mockService([
            'buildCreationOptionsJson' => '{"challenge":"dGVzdA","rp":{"name":"Test","id":"localhost"},"user":{"name":"test@test.com","id":"1","displayName":"Test"},"pubKeyCredParams":[]}',
            'verifyRegistration'       => $fakeRecord,
            'serializeRecord'          => '{"fake":"record"}',
        ]);

        Cache::put("webauthn_reg_{$this->user->id}", '{"cached":"options"}', 300);

        $this->actingAs($this->user)
            ->postJson('/api/auth/webauthn/register', [
                'credential' => '{"id":"dGVzdA","rawId":"dGVzdA","type":"public-key","response":{}}',
                'name'       => 'My iPhone',
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'My iPhone']);

        $this->assertDatabaseHas('webauthn_credentials', [
            'user_id' => $this->user->id,
            'name'    => 'My iPhone',
        ]);
    }

    public function test_register_fails_when_no_cached_options(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/auth/webauthn/register', ['credential' => '{}'])
            ->assertUnprocessable();
    }

    public function test_register_requires_auth(): void
    {
        $this->postJson('/api/auth/webauthn/register', ['credential' => '{}'])->assertUnauthorized();
    }

    // ── Authentication options ────────────────────────────────────────────────

    public function test_authentication_options_returns_challenge_and_session_id(): void
    {
        $this->postJson('/api/auth/webauthn/authenticate/options')
            ->assertOk()
            ->assertJsonStructure(['challenge', 'session_id']);
    }

    public function test_authentication_options_caches_by_session_id(): void
    {
        $response = $this->postJson('/api/auth/webauthn/authenticate/options')->json();

        $this->assertTrue(Cache::has("webauthn_auth_{$response['session_id']}"));
    }

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_authenticate_returns_token_on_success(): void
    {
        $fakeRecord = $this->makeFakeCredentialRecord((string) $this->user->id);

        $this->mockService([
            'deserializeRecord'   => $fakeRecord,
            'verifyAuthentication' => $fakeRecord,
            'serializeRecord'     => '{"fake":"record"}',
        ]);

        WebauthnCredential::create([
            'user_id'         => $this->user->id,
            'name'            => 'Test Passkey',
            'credential_id'   => base64_encode('testcredid'),
            'credential_data' => '{}',
        ]);

        $sessionId = 'test-session-uuid';
        Cache::put("webauthn_auth_{$sessionId}", '{"cached":"options"}', 300);

        $rawId = base64_encode('testcredid');

        $this->postJson('/api/auth/webauthn/authenticate', [
            'session_id' => $sessionId,
            'credential' => json_encode(['id' => $rawId, 'rawId' => $rawId, 'type' => 'public-key', 'response' => []]),
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_authenticate_fails_when_session_expired(): void
    {
        $this->postJson('/api/auth/webauthn/authenticate', [
            'session_id' => 'nonexistent-uuid',
            'credential' => '{}',
        ])->assertUnprocessable();
    }

    public function test_authenticate_fails_for_unknown_credential(): void
    {
        $sessionId = 'test-session';
        Cache::put("webauthn_auth_{$sessionId}", '{"cached":"options"}', 300);

        $this->postJson('/api/auth/webauthn/authenticate', [
            'session_id' => $sessionId,
            'credential' => json_encode(['id' => base64_encode('unknown'), 'rawId' => base64_encode('unknown')]),
        ])->assertUnauthorized();
    }

    // ── Credential management ─────────────────────────────────────────────────

    public function test_can_list_own_credentials(): void
    {
        WebauthnCredential::create(['user_id' => $this->user->id, 'name' => 'iPhone', 'credential_id' => 'cred1', 'credential_data' => '{}']);
        WebauthnCredential::create(['user_id' => $this->user->id, 'name' => 'iPad', 'credential_id' => 'cred2', 'credential_data' => '{}']);

        $this->actingAs($this->user)->getJson('/api/auth/webauthn/credentials')
            ->assertOk()->assertJsonCount(2);
    }

    public function test_cannot_see_other_users_credentials(): void
    {
        $other = User::factory()->create();
        WebauthnCredential::create(['user_id' => $other->id, 'name' => 'Other', 'credential_id' => 'cred-other', 'credential_data' => '{}']);

        $this->actingAs($this->user)->getJson('/api/auth/webauthn/credentials')
            ->assertOk()->assertJsonCount(0);
    }

    public function test_can_delete_own_credential(): void
    {
        $cred = WebauthnCredential::create(['user_id' => $this->user->id, 'name' => 'iPhone', 'credential_id' => 'cred1', 'credential_data' => '{}']);

        $this->actingAs($this->user)->deleteJson("/api/auth/webauthn/credentials/{$cred->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('webauthn_credentials', ['id' => $cred->id]);
    }

    public function test_cannot_delete_other_users_credential(): void
    {
        $other = User::factory()->create();
        $cred  = WebauthnCredential::create(['user_id' => $other->id, 'name' => 'Other', 'credential_id' => 'cred-other', 'credential_data' => '{}']);

        $this->actingAs($this->user)->deleteJson("/api/auth/webauthn/credentials/{$cred->id}")
            ->assertForbidden();
    }

    public function test_credential_endpoints_require_auth(): void
    {
        $this->getJson('/api/auth/webauthn/credentials')->assertUnauthorized();
        $this->deleteJson('/api/auth/webauthn/credentials/1')->assertUnauthorized();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeFakeCredentialRecord(string $userHandle = 'test-user'): CredentialRecord
    {
        $mock              = \Mockery::mock(CredentialRecord::class);
        $mock->publicKeyCredentialId = 'testcredid';
        $mock->userHandle            = $userHandle;
        $mock->counter               = 0;
        return $mock;
    }

    private function mockService(array $methods): void
    {
        $this->app->bind(WebauthnService::class, function () use ($methods) {
            $mock = \Mockery::mock(WebauthnService::class);
            foreach ($methods as $method => $return) {
                $mock->shouldReceive($method)->andReturn($return);
            }
            return $mock;
        });
    }
}
