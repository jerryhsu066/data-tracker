<?php

namespace Tests\Feature;

use App\Models\CashflowRecord;
use App\Models\CashflowSubtype;
use App\Models\CashflowType;
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
        $this->getJson('/api/cashflow/settings/types')->assertUnauthorized();
    }

    // ── Types ─────────────────────────────────────────────────────────────────

    public function test_can_list_own_types_with_subtypes(): void
    {
        $other = User::factory()->create();
        $type  = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Acme']);
        CashflowType::create(['user_id' => $other->id, 'name' => 'Other Type', 'is_expense' => true]);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/settings/types');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Income']);
        $response->assertJsonMissing(['name' => 'Other Type']);

        // subtypes nested
        $data = $response->json('0.subtypes');
        $this->assertCount(1, $data);
        $this->assertEquals('Acme', $data[0]['name']);
    }

    public function test_list_types_includes_unsubtyped_records_count(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        CashflowRecord::create(['user_id' => $this->user->id, 'cashflow_type_id' => $type->id, 'cashflow_subtype_id' => null, 'recorded_at' => now(), 'amount' => 100]);
        CashflowRecord::create(['user_id' => $this->user->id, 'cashflow_type_id' => $type->id, 'cashflow_subtype_id' => null, 'recorded_at' => now(), 'amount' => 200]);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/settings/types');

        $response->assertOk()->assertJsonPath('0.unsubtyped_records_count', 2);
    }

    public function test_list_types_unsubtyped_records_count_is_zero_when_no_records(): void
    {
        CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);

        $response = $this->actingAs($this->user)->getJson('/api/cashflow/settings/types');

        $response->assertOk()->assertJsonPath('0.unsubtyped_records_count', 0);
    }

    public function test_can_create_type(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/cashflow/settings/types', [
            'name'       => 'Subscription',
            'is_expense' => true,
        ]);

        $response->assertCreated()
                 ->assertJsonFragment(['name' => 'Subscription', 'is_expense' => true]);
        $this->assertDatabaseHas('cashflow_types', ['user_id' => $this->user->id, 'name' => 'Subscription']);
    }

    public function test_type_name_is_required(): void
    {
        $this->actingAs($this->user)->postJson('/api/cashflow/settings/types', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Old', 'is_expense' => true]);

        $response = $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonFragment(['name' => 'New Name']);
        $this->assertDatabaseHas('cashflow_types', ['id' => $type->id, 'name' => 'New Name']);
    }

    public function test_can_delete_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'To Delete', 'is_expense' => true]);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/types/{$type->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('cashflow_types', ['id' => $type->id]);
    }

    public function test_cannot_delete_type_with_existing_records(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        CashflowRecord::create(['user_id' => $this->user->id, 'cashflow_type_id' => $type->id, 'recorded_at' => now(), 'amount' => 100]);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/types/{$type->id}")
             ->assertUnprocessable()
             ->assertJsonFragment(['message' => 'This type has existing records. Disable it instead.']);

        $this->assertDatabaseHas('cashflow_types', ['id' => $type->id, 'deleted_at' => null]);
    }

    public function test_cannot_modify_another_users_type(): void
    {
        $other = User::factory()->create();
        $type  = CashflowType::create(['user_id' => $other->id, 'name' => 'Their Type', 'is_expense' => true]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['name' => 'Hijacked'])
             ->assertForbidden();
        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/types/{$type->id}")
             ->assertForbidden();
    }

    // ── Subtypes ──────────────────────────────────────────────────────────────

    public function test_can_create_subtype_under_own_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);

        $response = $this->actingAs($this->user)->postJson("/api/cashflow/settings/types/{$type->id}/subtypes", [
            'name' => 'Acme Corp',
        ]);

        $response->assertCreated()->assertJsonFragment(['name' => 'Acme Corp', 'cashflow_type_id' => $type->id]);
        $this->assertDatabaseHas('cashflow_subtypes', ['cashflow_type_id' => $type->id, 'name' => 'Acme Corp']);
    }

    public function test_create_subtype_response_includes_migrated_count(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);

        $response = $this->actingAs($this->user)->postJson("/api/cashflow/settings/types/{$type->id}/subtypes", [
            'name' => 'Acme',
        ]);

        $response->assertCreated()
                 ->assertJsonPath('migrated_count', 0)
                 ->assertJsonPath('subtype.name', 'Acme');
    }

    public function test_creating_first_subtype_with_migrate_true_reassigns_null_records(): void
    {
        $type   = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $record = CashflowRecord::create([
            'user_id'              => $this->user->id,
            'cashflow_type_id'     => $type->id,
            'cashflow_subtype_id'  => null,
            'recorded_at'          => now(),
            'amount'               => 100,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/cashflow/settings/types/{$type->id}/subtypes", [
            'name'             => 'Salary',
            'migrate_existing' => true,
        ]);

        $response->assertCreated()->assertJsonPath('migrated_count', 1);
        $subtypeId = $response->json('subtype.id');
        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'cashflow_subtype_id' => $subtypeId]);
    }

    public function test_creating_first_subtype_with_migrate_false_leaves_records_unchanged(): void
    {
        $type   = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $record = CashflowRecord::create([
            'user_id'              => $this->user->id,
            'cashflow_type_id'     => $type->id,
            'cashflow_subtype_id'  => null,
            'recorded_at'          => now(),
            'amount'               => 100,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/cashflow/settings/types/{$type->id}/subtypes", [
            'name'             => 'Salary',
            'migrate_existing' => false,
        ]);

        $response->assertCreated()->assertJsonPath('migrated_count', 0);
        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'cashflow_subtype_id' => null]);
    }

    public function test_migrate_existing_is_ignored_when_type_already_has_subtypes(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Existing']);
        $record = CashflowRecord::create([
            'user_id'              => $this->user->id,
            'cashflow_type_id'     => $type->id,
            'cashflow_subtype_id'  => null,
            'recorded_at'          => now(),
            'amount'               => 100,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/cashflow/settings/types/{$type->id}/subtypes", [
            'name'             => 'Second Sub',
            'migrate_existing' => true,
        ]);

        $response->assertCreated()->assertJsonPath('migrated_count', 0);
        $this->assertDatabaseHas('cashflow_records', ['id' => $record->id, 'cashflow_subtype_id' => null]);
    }

    public function test_cannot_add_subtype_to_another_users_type(): void
    {
        $other = User::factory()->create();
        $type  = CashflowType::create(['user_id' => $other->id, 'name' => 'Their Type', 'is_expense' => true]);

        $this->actingAs($this->user)->postJson("/api/cashflow/settings/types/{$type->id}/subtypes", ['name' => 'Sub'])
             ->assertForbidden();
    }

    public function test_can_update_subtype(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Old']);

        $response = $this->actingAs($this->user)->patchJson("/api/cashflow/settings/subtypes/{$subtype->id}", [
            'name' => 'New Sub',
        ]);

        $response->assertOk()->assertJsonFragment(['name' => 'New Sub']);
        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $subtype->id, 'name' => 'New Sub']);
    }

    public function test_can_delete_subtype(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Sub']);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/subtypes/{$subtype->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('cashflow_subtypes', ['id' => $subtype->id]);
    }

    public function test_cannot_delete_subtype_with_existing_records(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Salary']);
        CashflowRecord::create(['user_id' => $this->user->id, 'cashflow_type_id' => $type->id, 'cashflow_subtype_id' => $subtype->id, 'recorded_at' => now(), 'amount' => 100]);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/subtypes/{$subtype->id}")
             ->assertUnprocessable()
             ->assertJsonFragment(['message' => 'This subtype has existing records. Disable it instead.']);

        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $subtype->id, 'deleted_at' => null]);
    }

    public function test_cannot_modify_another_users_subtype(): void
    {
        $other   = User::factory()->create();
        $type    = CashflowType::create(['user_id' => $other->id, 'name' => 'Their Type', 'is_expense' => true]);
        $subtype = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $other->id, 'name' => 'Their Sub']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/subtypes/{$subtype->id}", ['name' => 'Hijacked'])
             ->assertForbidden();
        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/subtypes/{$subtype->id}")
             ->assertForbidden();
    }

    // ── Visibility / Privacy ──────────────────────────────────────────────────

    public function test_can_disable_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Subscription', 'is_expense' => true]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['is_disabled' => true])
             ->assertOk()->assertJsonFragment(['is_disabled' => true]);

        $this->assertDatabaseHas('cashflow_types', ['id' => $type->id, 'is_disabled' => true]);
    }

    public function test_can_set_private_on_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Subscription', 'is_expense' => true]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['is_private' => false])
             ->assertOk()->assertJsonFragment(['is_private' => false]);

        $this->assertDatabaseHas('cashflow_types', ['id' => $type->id, 'is_private' => false]);
    }

    public function test_can_set_merge_subtypes_on_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Subscription', 'is_expense' => true]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['merge_subtypes' => true])
             ->assertOk()->assertJsonFragment(['merge_subtypes' => true]);

        $this->assertDatabaseHas('cashflow_types', ['id' => $type->id, 'merge_subtypes' => true]);
    }

    public function test_can_disable_subtype(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Acme']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/subtypes/{$subtype->id}", ['is_disabled' => true])
             ->assertOk()->assertJsonFragment(['is_disabled' => true]);

        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $subtype->id, 'is_disabled' => true]);
    }

    public function test_disabling_type_cascades_to_subtypes(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $sub1 = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Sub1']);
        $sub2 = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Sub2']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['is_disabled' => true])
             ->assertOk();

        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $sub1->id, 'is_disabled' => true]);
        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $sub2->id, 'is_disabled' => true]);
    }

    public function test_setting_private_on_type_cascades_to_subtypes(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $sub  = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Sub']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['is_private' => false])
             ->assertOk();

        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $sub->id, 'is_private' => false]);
    }

    public function test_can_set_private_on_subtype(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['cashflow_type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Acme']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/subtypes/{$subtype->id}", ['is_private' => false])
             ->assertOk()->assertJsonFragment(['is_private' => false]);

        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $subtype->id, 'is_private' => false]);
    }
}
