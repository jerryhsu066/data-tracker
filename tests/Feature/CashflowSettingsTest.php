<?php

namespace Tests\Feature;

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
        CashflowSubtype::create(['type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Acme']);
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

        $response->assertCreated()->assertJsonFragment(['name' => 'Acme Corp', 'type_id' => $type->id]);
        $this->assertDatabaseHas('cashflow_subtypes', ['type_id' => $type->id, 'name' => 'Acme Corp']);
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
        $subtype = CashflowSubtype::create(['type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Old']);

        $response = $this->actingAs($this->user)->patchJson("/api/cashflow/settings/subtypes/{$subtype->id}", [
            'name' => 'New Sub',
        ]);

        $response->assertOk()->assertJsonFragment(['name' => 'New Sub']);
        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $subtype->id, 'name' => 'New Sub']);
    }

    public function test_can_delete_subtype(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Sub']);

        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/subtypes/{$subtype->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('cashflow_subtypes', ['id' => $subtype->id]);
    }

    public function test_cannot_modify_another_users_subtype(): void
    {
        $other   = User::factory()->create();
        $type    = CashflowType::create(['user_id' => $other->id, 'name' => 'Their Type', 'is_expense' => true]);
        $subtype = CashflowSubtype::create(['type_id' => $type->id, 'user_id' => $other->id, 'name' => 'Their Sub']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/subtypes/{$subtype->id}", ['name' => 'Hijacked'])
             ->assertForbidden();
        $this->actingAs($this->user)->deleteJson("/api/cashflow/settings/subtypes/{$subtype->id}")
             ->assertForbidden();
    }

    // ── Visibility ────────────────────────────────────────────────────────────

    public function test_can_hide_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Subscription', 'is_expense' => true]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['is_hidden' => true])
             ->assertOk()->assertJsonFragment(['is_hidden' => true]);

        $this->assertDatabaseHas('cashflow_types', ['id' => $type->id, 'is_hidden' => true]);
    }

    public function test_can_set_merge_subtypes_on_type(): void
    {
        $type = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Subscription', 'is_expense' => true]);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/types/{$type->id}", ['merge_subtypes' => true])
             ->assertOk()->assertJsonFragment(['merge_subtypes' => true]);

        $this->assertDatabaseHas('cashflow_types', ['id' => $type->id, 'merge_subtypes' => true]);
    }

    public function test_can_hide_subtype(): void
    {
        $type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);
        $subtype = CashflowSubtype::create(['type_id' => $type->id, 'user_id' => $this->user->id, 'name' => 'Acme']);

        $this->actingAs($this->user)->patchJson("/api/cashflow/settings/subtypes/{$subtype->id}", ['is_hidden' => true])
             ->assertOk()->assertJsonFragment(['is_hidden' => true]);

        $this->assertDatabaseHas('cashflow_subtypes', ['id' => $subtype->id, 'is_hidden' => true]);
    }
}
