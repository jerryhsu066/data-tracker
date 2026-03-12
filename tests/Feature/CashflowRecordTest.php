<?php

namespace Tests\Feature;

use App\Models\CashflowRecord;
use App\Models\CashflowSubtype;
use App\Models\CashflowType;
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

    public function test_can_list_records_for_a_year(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);

        CashflowRecord::create(['user_id' => $this->user->id, 'recorded_at' => '2026-03-01', 'type_id' => $type->id, 'amount' => 80000]);
        CashflowRecord::create(['user_id' => $this->user->id, 'recorded_at' => '2026-04-01', 'type_id' => $type->id, 'amount' => 80000]);
        CashflowRecord::create(['user_id' => $this->user->id, 'recorded_at' => '2025-03-01', 'type_id' => $type->id, 'amount' => 75000]);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/records?year=2026');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_can_list_records_for_a_month(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);

        CashflowRecord::create(['user_id' => $this->user->id, 'recorded_at' => '2026-03-01', 'type_id' => $type->id, 'amount' => 80000]);
        CashflowRecord::create(['user_id' => $this->user->id, 'recorded_at' => '2026-04-01', 'type_id' => $type->id, 'amount' => 80000]);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/records?year=2026&month=3');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['amount' => '80000.00']);
    }

    public function test_only_sees_own_records(): void
    {
        $other = User::factory()->create();
        $type  = CashflowType::create(['user_id' => $other->id, 'name' => 'Rent', 'is_expense' => true]);
        CashflowRecord::create(['user_id' => $other->id, 'recorded_at' => '2026-03-01', 'type_id' => $type->id, 'amount' => 20000]);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/records?year=2026');

        $response->assertOk()->assertJsonCount(0);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_can_create_record_without_subtype(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Rent', 'is_expense' => true]);

        $response = $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type_id'     => $type->id,
            'amount'      => 25000,
        ]);

        $response->assertCreated()
                 ->assertJsonStructure(['id', 'type_id', 'subtype_id', 'amount', 'recorded_at', 'note'])
                 ->assertJsonFragment(['amount' => '25000.00', 'subtype_id' => null]);

        $this->assertDatabaseHas('cashflow_records', ['user_id' => $this->user->id, 'type_id' => $type->id]);
    }

    public function test_can_create_record_with_subtype(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Acme']);

        $response = $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type_id'     => $type->id,
            'subtype_id'  => $subtype->id,
            'amount'      => 80000,
        ]);

        $response->assertCreated()->assertJsonFragment(['subtype_id' => $subtype->id]);
        $this->assertDatabaseHas('cashflow_records', ['subtype_id' => $subtype->id]);
    }

    public function test_cannot_use_another_users_type(): void
    {
        $other = User::factory()->create();
        $type  = CashflowType::create(['user_id' => $other->id, 'name' => 'Rent', 'is_expense' => true]);

        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type_id'     => $type->id,
            'amount'      => 25000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type_id']);
    }

    public function test_cannot_use_subtype_from_different_type(): void
    {
        $typeA   = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $typeB   = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Card', 'is_expense' => true]);
        $subtype = CashflowSubtype::create(['type_id' => $typeB->id, 'user_id' => $this->user->id, 'name' => 'CTBC']);

        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type_id'     => $typeA->id,
            'subtype_id'  => $subtype->id,
            'amount'      => 80000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['subtype_id']);
    }

    public function test_type_id_is_required(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'amount'      => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type_id']);
    }

    public function test_cannot_create_record_without_required_subtype(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Card', 'is_expense' => true]);
        CashflowSubtype::create(['type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'HSBC']);

        $this->actingAs($this->user)->postJson('/api/cashflow/records', [
            'recorded_at' => '2026-03-01',
            'type_id'     => $type->id,
            'amount'      => 5000,
            // subtype_id intentionally omitted
        ])->assertUnprocessable()->assertJsonValidationErrors(['subtype_id']);
    }

    public function test_cannot_change_type_to_one_with_subtypes_without_providing_subtype_id(): void
    {
        $typeNoSub  = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Rent',   'is_expense' => true]);
        $typeWithSub = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Card',  'is_expense' => true]);
        CashflowSubtype::create(['type_id' => $typeWithSub->id, 'user_id' => $this->user->id, 'name' => 'HSBC']);

        $record = CashflowRecord::create([
            'user_id' => $this->user->id, 'recorded_at' => '2026-03-01',
            'type_id' => $typeNoSub->id, 'amount' => 25000,
        ]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/records/{$record->id}", [
            'type_id' => $typeWithSub->id,
            // subtype_id intentionally omitted
        ])->assertUnprocessable()->assertJsonValidationErrors(['subtype_id']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_can_update_record(): void
    {
        $type   = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Rent', 'is_expense' => true]);
        $record = CashflowRecord::create(['user_id' => $this->user->id, 'recorded_at' => '2026-03-01', 'type_id' => $type->id, 'amount' => 25000]);

        $response = $this->actingAs($this->user)->patchJson("/api/cashflow/records/{$record->id}", [
            'amount' => 26000,
            'note'   => 'Price increase',
        ]);

        $response->assertOk()->assertJsonFragment(['amount' => '26000.00', 'note' => 'Price increase']);
        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'amount' => 26000]);
    }

    public function test_cannot_update_another_users_record(): void
    {
        $other  = User::factory()->create();
        $type   = CashflowType::create(['user_id' => $other->id, 'name' => 'Rent', 'is_expense' => true]);
        $record = CashflowRecord::create(['user_id' => $other->id, 'recorded_at' => '2026-03-01', 'type_id' => $type->id, 'amount' => 25000]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/records/{$record->id}", ['amount' => 1])
             ->assertForbidden();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_can_delete_record(): void
    {
        $type   = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Rent', 'is_expense' => true]);
        $record = CashflowRecord::create(['user_id' => $this->user->id, 'recorded_at' => '2026-03-01', 'type_id' => $type->id, 'amount' => 25000]);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/records/{$record->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('cashflow_records', ['id' => $record->id]);
    }

    public function test_cannot_delete_another_users_record(): void
    {
        $other  = User::factory()->create();
        $type   = CashflowType::create(['user_id' => $other->id, 'name' => 'Rent', 'is_expense' => true]);
        $record = CashflowRecord::create(['user_id' => $other->id, 'recorded_at' => '2026-03-01', 'type_id' => $type->id, 'amount' => 25000]);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/records/{$record->id}")
             ->assertForbidden();
    }
}
