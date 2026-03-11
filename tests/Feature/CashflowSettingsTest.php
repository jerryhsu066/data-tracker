<?php

namespace Tests\Feature;

use App\Models\CashflowBank;
use App\Models\CashflowCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashflowSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_cashflow_settings(): void
    {
        $this->getJson('/api/cashflow/settings/companies')->assertUnauthorized();
        $this->getJson('/api/cashflow/settings/banks')->assertUnauthorized();
    }

    // ── Companies ─────────────────────────────────────────────────────────────

    public function test_can_list_own_companies(): void
    {
        $other = User::factory()->create();
        CashflowCompany::create(['user_id' => $this->user->id, 'name' => 'My Corp']);
        CashflowCompany::create(['user_id' => $other->id, 'name' => 'Other Corp']);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/settings/companies');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'My Corp']);
        $response->assertJsonMissing(['name' => 'Other Corp']);
    }

    public function test_can_create_company(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/cashflow/settings/companies', [
            'name' => 'Acme Inc',
        ]);

        $response->assertCreated()->assertJsonFragment(['name' => 'Acme Inc']);
        $this->assertDatabaseHas('cashflow_companies', ['user_id' => $this->user->id, 'name' => 'Acme Inc']);
    }

    public function test_company_name_is_required(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/settings/companies', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_company(): void
    {
        $company = CashflowCompany::create(['user_id' => $this->user->id, 'name' => 'Old Name']);

        $response = $this->actingAs($this->user)->patchJson("/api/cashflow/settings/companies/{$company->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonFragment(['name' => 'New Name']);
        $this->assertDatabaseHas('cashflow_companies', ['id' => $company->id, 'name' => 'New Name']);
    }

    public function test_can_delete_company(): void
    {
        $company = CashflowCompany::create(['user_id' => $this->user->id, 'name' => 'To Delete']);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/companies/{$company->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('cashflow_companies', ['id' => $company->id]);
    }

    public function test_cannot_modify_another_users_company(): void
    {
        $other = User::factory()->create();
        $company = CashflowCompany::create(['user_id' => $other->id, 'name' => 'Their Corp']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/companies/{$company->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/companies/{$company->id}")
            ->assertForbidden();
    }

    // ── Banks ─────────────────────────────────────────────────────────────────

    public function test_can_list_own_banks(): void
    {
        $other = User::factory()->create();
        CashflowBank::create(['user_id' => $this->user->id, 'name' => 'My Bank']);
        CashflowBank::create(['user_id' => $other->id, 'name' => 'Other Bank']);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/settings/banks');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'My Bank']);
        $response->assertJsonMissing(['name' => 'Other Bank']);
    }

    public function test_can_create_bank(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/cashflow/settings/banks', [
            'name' => 'CTBC',
        ]);

        $response->assertCreated()->assertJsonFragment(['name' => 'CTBC']);
        $this->assertDatabaseHas('cashflow_banks', ['user_id' => $this->user->id, 'name' => 'CTBC']);
    }

    public function test_bank_name_is_required(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/settings/banks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_bank(): void
    {
        $bank = CashflowBank::create(['user_id' => $this->user->id, 'name' => 'Old Bank']);

        $response = $this->actingAs($this->user)->patchJson("/api/cashflow/settings/banks/{$bank->id}", [
            'name' => 'New Bank',
        ]);

        $response->assertOk()->assertJsonFragment(['name' => 'New Bank']);
        $this->assertDatabaseHas('cashflow_banks', ['id' => $bank->id, 'name' => 'New Bank']);
    }

    public function test_can_delete_bank(): void
    {
        $bank = CashflowBank::create(['user_id' => $this->user->id, 'name' => 'To Delete']);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/banks/{$bank->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('cashflow_banks', ['id' => $bank->id]);
    }

    public function test_cannot_modify_another_users_bank(): void
    {
        $other = User::factory()->create();
        $bank = CashflowBank::create(['user_id' => $other->id, 'name' => 'Their Bank']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/banks/{$bank->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/banks/{$bank->id}")
            ->assertForbidden();
    }
}
