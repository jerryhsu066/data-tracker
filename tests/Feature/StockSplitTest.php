<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\StockSplit;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stock = Stock::factory()->create(['symbol' => '2330.TW']);
    }

    // --- CRUD ---

    public function test_can_list_splits_for_a_stock(): void
    {
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2024-01-15', 'ratio_from' => 1, 'ratio_to' => 2]);
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2025-06-01', 'ratio_from' => 1, 'ratio_to' => 3]);

        $this->actingAs($this->user)
            ->getJson('/api/stocks/2330.TW/splits')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['ratio_from' => 1, 'ratio_to' => 2])
            ->assertJsonFragment(['ratio_from' => 1, 'ratio_to' => 3]);
    }

    public function test_can_create_a_split(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/stocks/2330.TW/splits', [
                'split_date' => '2025-06-01',
                'ratio_from' => 1,
                'ratio_to'   => 2,
            ])
            ->assertCreated()
            ->assertJsonFragment(['split_date' => '2025-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);

        $this->assertDatabaseHas('stock_splits', [
            'stock_id'   => $this->stock->id,
            'split_date' => '2025-06-01',
            'ratio_from' => 1,
            'ratio_to'   => 2,
        ]);
    }

    public function test_can_delete_a_split(): void
    {
        $split = StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2025-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);

        $this->actingAs($this->user)
            ->deleteJson("/api/stocks/2330.TW/splits/{$split->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('stock_splits', ['id' => $split->id]);
    }

    public function test_split_requires_valid_ratios(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/stocks/2330.TW/splits', [
                'split_date' => '2025-06-01',
                'ratio_from' => 0,
                'ratio_to'   => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ratio_from']);

        $this->actingAs($this->user)
            ->postJson('/api/stocks/2330.TW/splits', [
                'split_date' => '2025-06-01',
                'ratio_from' => 1,
                'ratio_to'   => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ratio_to']);
    }

    public function test_split_requires_a_date(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/stocks/2330.TW/splits', [
                'ratio_from' => 1,
                'ratio_to'   => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['split_date']);
    }

    public function test_unauthenticated_users_cannot_manage_splits(): void
    {
        $this->getJson('/api/stocks/2330.TW/splits')->assertUnauthorized();
        $this->postJson('/api/stocks/2330.TW/splits', [])->assertUnauthorized();
    }

    // --- Portfolio adjustments ---

    public function test_portfolio_adjusts_shares_after_a_2_for_1_split(): void
    {
        // Bought 1000 shares @ $50 before a 2:1 split
        StockTransaction::factory()->buy($this->stock, shares: 1000, price: 50)
            ->create(['user_id' => $this->user->id, 'transacted_at' => '2024-01-01']);

        StockSplit::create([
            'stock_id'   => $this->stock->id,
            'split_date' => '2024-06-01',
            'ratio_from' => 1,
            'ratio_to'   => 2,
        ]);

        $this->stock->update(['current_price' => 25.0]);

        $position = $this->actingAs($this->user)
            ->getJson('/api/stocks/portfolio')
            ->assertOk()
            ->json()[0];

        // After a 2:1 split: 2000 shares @ avg cost $25
        $this->assertEquals('2000.0000', $position['net_shares']);
        $this->assertEqualsWithDelta(25.0, (float) $position['average_cost'], 0.001);
        // Current value: 2000 × $25 = $50,000
        $this->assertEqualsWithDelta(50000.0, (float) $position['current_value'], 0.001);
    }

    public function test_portfolio_applies_chained_splits(): void
    {
        // Bought 1000 shares @ $60 before two splits: 2:1 then 3:1 → 6× total
        StockTransaction::factory()->buy($this->stock, shares: 1000, price: 60)
            ->create(['user_id' => $this->user->id, 'transacted_at' => '2023-01-01']);

        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2023-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2024-06-01', 'ratio_from' => 1, 'ratio_to' => 3]);

        $this->stock->update(['current_price' => 10.0]);

        $position = $this->actingAs($this->user)
            ->getJson('/api/stocks/portfolio')
            ->assertOk()
            ->json()[0];

        $this->assertEquals('6000.0000', $position['net_shares']);
        $this->assertEqualsWithDelta(10.0, (float) $position['average_cost'], 0.001);
    }

    public function test_portfolio_does_not_adjust_transactions_after_split(): void
    {
        // Split on June 1; buy after the split should not be double-counted
        StockSplit::create([
            'stock_id'   => $this->stock->id,
            'split_date' => '2024-06-01',
            'ratio_from' => 1,
            'ratio_to'   => 2,
        ]);

        StockTransaction::factory()->buy($this->stock, shares: 1000, price: 25)
            ->create(['user_id' => $this->user->id, 'transacted_at' => '2024-07-01']);

        $this->stock->update(['current_price' => 25.0]);

        $position = $this->actingAs($this->user)
            ->getJson('/api/stocks/portfolio')
            ->assertOk()
            ->json()[0];

        $this->assertEquals('1000.0000', $position['net_shares']);
    }

    public function test_portfolio_handles_mixed_pre_and_post_split_transactions(): void
    {
        // Buy 1000 pre-split → becomes 2000 after 2:1
        StockTransaction::factory()->buy($this->stock, shares: 1000, price: 50)
            ->create(['user_id' => $this->user->id, 'transacted_at' => '2024-01-01']);

        StockSplit::create([
            'stock_id'   => $this->stock->id,
            'split_date' => '2024-06-01',
            'ratio_from' => 1,
            'ratio_to'   => 2,
        ]);

        // Buy 500 post-split → stays 500
        StockTransaction::factory()->buy($this->stock, shares: 500, price: 25)
            ->create(['user_id' => $this->user->id, 'transacted_at' => '2024-07-01']);

        $this->stock->update(['current_price' => 25.0]);

        $position = $this->actingAs($this->user)
            ->getJson('/api/stocks/portfolio')
            ->assertOk()
            ->json()[0];

        $this->assertEquals('2500.0000', $position['net_shares']);
    }

    // --- Sell validation ---

    public function test_cannot_sell_more_than_split_adjusted_shares(): void
    {
        StockTransaction::factory()->buy($this->stock, shares: 1000, price: 50)
            ->create(['user_id' => $this->user->id, 'transacted_at' => '2024-01-01']);

        StockSplit::create([
            'stock_id'   => $this->stock->id,
            'split_date' => '2024-06-01',
            'ratio_from' => 1,
            'ratio_to'   => 2,
        ]);

        // Trying to sell 2001 but only holds 2000 after split
        $this->actingAs($this->user)
            ->postJson('/api/stocks/transactions', [
                'stock_id'        => $this->stock->id,
                'type'            => 'sell',
                'shares'          => 2001,
                'price_per_share' => 25,
                'transacted_at'   => '2024-07-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shares']);
    }

    public function test_can_sell_up_to_split_adjusted_shares(): void
    {
        StockTransaction::factory()->buy($this->stock, shares: 1000, price: 50)
            ->create(['user_id' => $this->user->id, 'transacted_at' => '2024-01-01']);

        StockSplit::create([
            'stock_id'   => $this->stock->id,
            'split_date' => '2024-06-01',
            'ratio_from' => 1,
            'ratio_to'   => 2,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/stocks/transactions', [
                'stock_id'        => $this->stock->id,
                'type'            => 'sell',
                'shares'          => 2000,
                'price_per_share' => 25,
                'transacted_at'   => '2024-07-01',
            ])
            ->assertCreated();
    }
}
