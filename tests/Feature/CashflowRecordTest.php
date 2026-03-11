<?php

namespace Tests\Feature;

use App\Models\CashflowBank;
use App\Models\CashflowCompany;
use App\Models\CashflowRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashflowRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_records(): void
    {
        $this->getJson('/api/cashflow/records')->assertUnauthorized();
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_can_list_records_for_a_month(): void
    {
        $company = CashflowCompany::create(['user_id' => $this->user->id, 'name' => 'Acme']);

        CashflowRecord::create([
            'user_id'     => $this->user->id,
            'recorded_at' => '2026-03-01',
            'type'        => 'income',
            'amount'      => 80000,
            'company_id'  => $company->id,
        ]);
        CashflowRecord::create([
            'user_id'     => $this->user->id,
            'recorded_at' => '2026-04-01',
            'type'        => 'rent',
            'amount'      => 25000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/cashflow/records?year=2026&month=3');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['type' => 'income']);
    }

    public function test_only_sees_own_records(): void
    {
        $other = User::factory()->create();
        CashflowRecord::create([
            'user_id'     => $other->id,
            'recorded_at' => '2026-03-01',
            'type'        => 'rent',
            'amount'      => 20000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/cashflow/records?year=2026&month=3');

        $response->assertOk()->assertJsonCount(0);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_can_create_income_record(): void
    {
        $company = CashflowCompany::create(['user_id' => $this->user->id, 'name' => 'Acme']);

        $response = $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'income',
            'amount'      => 80000,
            'company_id'  => $company->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'type', 'amount', 'recorded_at', 'company', 'bank', 'note'])
            ->assertJsonFragment(['type' => 'income', 'amount' => '80000.00']);

        $this->assertDatabaseHas('cashflow_records', [
            'user_id'    => $this->user->id,
            'type'       => 'income',
            'company_id' => $company->id,
        ]);
    }

    public function test_can_create_credit_card_record(): void
    {
        $bank = CashflowBank::create(['user_id' => $this->user->id, 'name' => 'CTBC']);

        $response = $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'credit_card',
            'amount'      => 15000,
            'bank_id'     => $bank->id,
        ]);

        $response->assertCreated()->assertJsonFragment(['type' => 'credit_card']);
        $this->assertDatabaseHas('cashflow_records', ['bank_id' => $bank->id]);
    }

    public function test_can_create_rent_record(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'rent',
            'amount'      => 25000,
        ]);

        $response->assertCreated()->assertJsonFragment(['type' => 'rent']);
    }

    public function test_income_requires_company_id(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'income',
            'amount'      => 80000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id']);
    }

    public function test_credit_card_requires_bank_id(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'credit_card',
            'amount'      => 15000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['bank_id']);
    }

    public function test_cannot_use_another_users_company(): void
    {
        $other = User::factory()->create();
        $company = CashflowCompany::create(['user_id' => $other->id, 'name' => 'Their Corp']);

        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'income',
            'amount'      => 80000,
            'company_id'  => $company->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id']);
    }

    public function test_cannot_use_another_users_bank(): void
    {
        $other = User::factory()->create();
        $bank = CashflowBank::create(['user_id' => $other->id, 'name' => 'Their Bank']);

        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'credit_card',
            'amount'      => 15000,
            'bank_id'     => $bank->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['bank_id']);
    }

    public function test_type_must_be_valid(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type'        => 'invalid_type',
            'amount'      => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_can_update_record(): void
    {
        $record = CashflowRecord::create([
            'user_id'     => $this->user->id,
            'recorded_at' => '2026-03-01',
            'type'        => 'rent',
            'amount'      => 25000,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/cashflow/records/{$record->id}", [
            'amount' => 26000,
            'note'   => 'Price increase',
        ]);

        $response->assertOk()->assertJsonFragment(['amount' => '26000.00', 'note' => 'Price increase']);
        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'amount' => 26000]);
    }

    public function test_cannot_update_another_users_record(): void
    {
        $other = User::factory()->create();
        $record = CashflowRecord::create([
            'user_id'     => $other->id,
            'recorded_at' => '2026-03-01',
            'type'        => 'rent',
            'amount'      => 25000,
        ]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/records/{$record->id}", ['amount' => 1])
            ->assertForbidden();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_can_delete_record(): void
    {
        $record = CashflowRecord::create([
            'user_id'     => $this->user->id,
            'recorded_at' => '2026-03-01',
            'type'        => 'rent',
            'amount'      => 25000,
        ]);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/records/{$record->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('cashflow_records', ['id' => $record->id]);
    }

    public function test_cannot_delete_another_users_record(): void
    {
        $other = User::factory()->create();
        $record = CashflowRecord::create([
            'user_id'     => $other->id,
            'recorded_at' => '2026-03-01',
            'type'        => 'rent',
            'amount'      => 25000,
        ]);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/records/{$record->id}")
            ->assertForbidden();
    }
}
