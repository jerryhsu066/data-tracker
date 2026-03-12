<?php

namespace Tests\Feature;

use App\Models\CashflowRecord;
use App\Models\CashflowSubtype;
use App\Models\CashflowType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashflowRecordBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CashflowType $type;
    private CashflowSubtype $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $this->sub  = CashflowSubtype::create(['cashflow_type_id' => $this->type->id, 'user_id' => $this->user->id, 'name' => 'Salary']);
    }

    public function test_bulk_creates_records(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/cashflow/records/bulk', [
            'year'    => 2026,
            'month'   => 3,
            'creates' => [
                ['cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => $this->sub->id, 'amount' => 5000, 'note' => 'March pay'],
                ['cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => $this->sub->id, 'amount' => 200,  'note' => null],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cashflow_records', ['cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => $this->sub->id, 'amount' => 5000]);
        $this->assertDatabaseHas('cashflow_records', ['cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => $this->sub->id, 'amount' => 200]);
        $this->assertCount(2, $response->json('created'));
    }

    public function test_bulk_cannot_create_record_without_required_subtype(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/records/bulk', [
            'year'    => 2026,
            'month'   => 3,
            'creates' => [
                ['cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => null, 'amount' => 5000, 'note' => null],
            ],
        ])->assertUnprocessable();
    }

    public function test_bulk_updates_records(): void
    {
        $record = CashflowRecord::create([
            'user_id' => $this->user->id, 'recorded_at' => '2026-03-01',
            'cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => null, 'amount' => 100,
        ]);

        $this->actingAs($this->user)->postJson('/api/cashflow/records/bulk', [
            'year'    => 2026,
            'month'   => 3,
            'updates' => [['id' => $record->id, 'amount' => 999, 'note' => 'Updated']],
        ])->assertOk();

        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'amount' => 999, 'note' => 'Updated']);
    }

    public function test_bulk_deletes_records(): void
    {
        $record = CashflowRecord::create([
            'user_id' => $this->user->id, 'recorded_at' => '2026-03-01',
            'cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => null, 'amount' => 100,
        ]);

        $this->actingAs($this->user)->postJson('/api/cashflow/records/bulk', [
            'year'    => 2026,
            'month'   => 3,
            'deletes' => [$record->id],
        ])->assertOk();

        $this->assertSoftDeleted('cashflow_records', ['id' => $record->id]);
    }

    public function test_bulk_handles_mixed_operations(): void
    {
        $existing = CashflowRecord::create([
            'user_id' => $this->user->id, 'recorded_at' => '2026-03-01',
            'cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => null, 'amount' => 100,
        ]);
        $toDelete = CashflowRecord::create([
            'user_id' => $this->user->id, 'recorded_at' => '2026-03-01',
            'cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => null, 'amount' => 50,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/cashflow/records/bulk', [
            'year'    => 2026,
            'month'   => 3,
            'creates' => [['cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => $this->sub->id, 'amount' => 300, 'note' => null]],
            'updates' => [['id' => $existing->id, 'amount' => 200, 'note' => null]],
            'deletes' => [$toDelete->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cashflow_records', ['id' => $existing->id, 'amount' => 200]);
        $this->assertSoftDeleted('cashflow_records', ['id' => $toDelete->id]);
        $this->assertCount(1, $response->json('created'));
    }

    public function test_bulk_cannot_modify_another_users_records(): void
    {
        $other  = User::factory()->create();
        $record = CashflowRecord::create([
            'user_id' => $other->id, 'recorded_at' => '2026-03-01',
            'cashflow_type_id' => $this->type->id, 'cashflow_subtype_id' => null, 'amount' => 100,
        ]);

        // update: silently skipped (no 403, just not applied)
        $this->actingAs($this->user)->postJson('/api/cashflow/records/bulk', [
            'year' => 2026, 'month' => 3,
            'updates' => [['id' => $record->id, 'amount' => 999, 'note' => null]],
        ])->assertOk();

        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'amount' => 100]);

        // delete: silently skipped
        $this->actingAs($this->user)->postJson('/api/cashflow/records/bulk', [
            'year' => 2026, 'month' => 3,
            'deletes' => [$record->id],
        ])->assertOk();

        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'deleted_at' => null]);
    }

    public function test_bulk_requires_auth(): void
    {
        $this->postJson('/api/cashflow/records/bulk', [])->assertUnauthorized();
    }
}
