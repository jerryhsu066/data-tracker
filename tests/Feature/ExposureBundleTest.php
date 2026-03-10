<?php

namespace Tests\Feature;

use App\Models\ExposureBundle;
use App\Models\ExposureBundleEntry;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExposureBundleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_bundles(): void
    {
        $this->getJson('/api/stocks/exposure/bundles')->assertUnauthorized();
    }

    public function test_can_list_own_bundles(): void
    {
        $other = User::factory()->create();

        ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'My Bundle', 'cash' => 0]);
        ExposureBundle::create(['user_id' => $other->id, 'name' => 'Other Bundle', 'cash' => 0]);

        $response = $this->actingAs($this->user)->getJson('/api/stocks/exposure/bundles');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'My Bundle']);
        $response->assertJsonMissing(['name' => 'Other Bundle']);
    }

    public function test_create_bundle(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/stocks/exposure/bundles', [
            'name' => 'Main Portfolio',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'name', 'cash', 'entries'])
            ->assertJsonFragment(['name' => 'Main Portfolio', 'cash' => 0]);

        $this->assertDatabaseHas('exposure_bundles', [
            'user_id' => $this->user->id,
            'name' => 'Main Portfolio',
        ]);
    }

    public function test_update_bundle_name_and_cash(): void
    {
        $bundle = ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'Old Name', 'cash' => 0]);

        $response = $this->actingAs($this->user)->patchJson("/api/stocks/exposure/bundles/{$bundle->id}", [
            'name' => 'New Name',
            'cash' => 100000,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name', 'cash' => 100000]);

        $this->assertDatabaseHas('exposure_bundles', [
            'id' => $bundle->id,
            'name' => 'New Name',
            'cash' => 100000,
        ]);
    }

    public function test_delete_bundle(): void
    {
        $bundle = ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'To Delete', 'cash' => 0]);

        $this->actingAs($this->user)->deleteJson("/api/stocks/exposure/bundles/{$bundle->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('exposure_bundles', ['id' => $bundle->id]);
    }

    public function test_cannot_modify_another_users_bundle(): void
    {
        $other = User::factory()->create();
        $bundle = ExposureBundle::create(['user_id' => $other->id, 'name' => 'Their Bundle', 'cash' => 0]);

        $this->actingAs($this->user)->patchJson("/api/stocks/exposure/bundles/{$bundle->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($this->user)->deleteJson("/api/stocks/exposure/bundles/{$bundle->id}")
            ->assertForbidden();
    }

    public function test_add_entry_to_bundle(): void
    {
        $bundle = ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'Bundle', 'cash' => 0]);
        $stock = Stock::factory()->create(['symbol' => '0050.TW', 'name' => '元大台灣50']);

        $response = $this->actingAs($this->user)->postJson("/api/stocks/exposure/bundles/{$bundle->id}/entries", [
            'stock_id' => $stock->id,
            'leverage' => 1.0,
            'is_cash' => false,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'id', 'name', 'cash',
                'entries' => [['id', 'stock', 'leverage', 'is_cash', 'net_shares']],
            ]);

        $this->assertDatabaseHas('exposure_bundle_entries', [
            'bundle_id' => $bundle->id,
            'stock_id' => $stock->id,
        ]);
    }

    public function test_net_shares_computed_from_transactions(): void
    {
        $bundle = ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'Bundle', 'cash' => 0]);
        $stock = Stock::factory()->create(['symbol' => '0050.TW']);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 1000,
            'price_per_share' => 73,
            'transacted_at' => '2026-01-01',
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/stocks/exposure/bundles/{$bundle->id}/entries", [
            'stock_id' => $stock->id,
            'leverage' => 1.0,
            'is_cash' => false,
        ]);

        $response->assertOk();

        $entry = collect($response->json('entries'))->firstWhere('stock.id', $stock->id);
        $this->assertNotNull($entry);
        $this->assertEquals('1000.0000', $entry['net_shares']);
    }

    public function test_add_entry_with_shares_override(): void
    {
        $bundle = ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'Bundle', 'cash' => 0]);
        $stock = Stock::factory()->create(['symbol' => '0050.TW']);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 1000,
            'price_per_share' => 73,
            'transacted_at' => '2026-01-01',
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/stocks/exposure/bundles/{$bundle->id}/entries", [
            'stock_id'        => $stock->id,
            'leverage'        => 1.0,
            'is_cash'         => false,
            'shares_override' => 500,
        ]);

        $response->assertOk();
        $entry = collect($response->json('entries'))->firstWhere('stock.id', $stock->id);
        $this->assertEquals('500.0000', $entry['net_shares']);
        $this->assertEquals('500.0000', $entry['shares_override']);
    }

    public function test_update_entry_shares_override(): void
    {
        $bundle = ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'Bundle', 'cash' => 0]);
        $stock = Stock::factory()->create();

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 1000,
            'price_per_share' => 73,
            'transacted_at' => '2026-01-01',
        ]);

        $entry = ExposureBundleEntry::create([
            'bundle_id' => $bundle->id,
            'stock_id'  => $stock->id,
            'leverage'  => 1.0,
            'is_cash'   => false,
        ]);

        // Override to 300
        $response = $this->actingAs($this->user)->patchJson(
            "/api/stocks/exposure/bundles/{$bundle->id}/entries/{$entry->id}",
            ['shares_override' => 300]
        );
        $response->assertOk();
        $updated = collect($response->json('entries'))->firstWhere('id', $entry->id);
        $this->assertEquals('300.0000', $updated['net_shares']);
        $this->assertEquals('300.0000', $updated['shares_override']);

        // Reset to auto (null)
        $response = $this->actingAs($this->user)->patchJson(
            "/api/stocks/exposure/bundles/{$bundle->id}/entries/{$entry->id}",
            ['shares_override' => null]
        );
        $response->assertOk();
        $updated = collect($response->json('entries'))->firstWhere('id', $entry->id);
        $this->assertEquals('1000.0000', $updated['net_shares']);
        $this->assertNull($updated['shares_override']);
    }

    public function test_remove_entry_from_bundle(): void
    {
        $bundle = ExposureBundle::create(['user_id' => $this->user->id, 'name' => 'Bundle', 'cash' => 0]);
        $stock = Stock::factory()->create();
        $entry = ExposureBundleEntry::create([
            'bundle_id' => $bundle->id,
            'stock_id' => $stock->id,
            'leverage' => 1.0,
            'is_cash' => false,
        ]);

        $this->actingAs($this->user)->deleteJson("/api/stocks/exposure/bundles/{$bundle->id}/entries/{$entry->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('exposure_bundle_entries', ['id' => $entry->id]);
    }
}
