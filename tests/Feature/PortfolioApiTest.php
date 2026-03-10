<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_portfolio_shows_aggregated_position_per_stock(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW', 'current_price' => 900.0000]);

        // Buy 1000 @ 800, buy 500 @ 820 → WAC = (800000+410000)/1500 = 806.67
        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create(['user_id' => $this->user->id]);
        Transaction::factory()->buy($stock, shares: 500, price: 820)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/stocks/portfolio');

        $response->assertOk()->assertJsonCount(1);

        $position = $response->json()[0];

        $this->assertEquals('2330.TW', $position['stock']['symbol']);
        $this->assertEquals('1500.0000', $position['net_shares']);
        $this->assertEqualsWithDelta(806.6667, (float) $position['average_cost'], 0.001);
        $this->assertEquals('1350000.0000', $position['current_value']);
    }

    public function test_portfolio_deducts_sold_shares(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW', 'current_price' => 900.0000]);

        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create(['user_id' => $this->user->id]);
        Transaction::factory()->sell($stock, shares: 300, price: 850)->create(['user_id' => $this->user->id]);

        $position = $this->actingAs($this->user)->getJson('/api/stocks/portfolio')->json()[0];

        $this->assertEquals('700.0000', $position['net_shares']);
        $this->assertEquals('800.0000', $position['average_cost']);
    }

    public function test_portfolio_calculates_unrealized_and_realized_gain(): void
    {
        $stock = Stock::factory()->create(['current_price' => 900.0000]);

        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create(['user_id' => $this->user->id]);
        Transaction::factory()->sell($stock, shares: 200, price: 850)->create(['user_id' => $this->user->id]);

        $position = $this->actingAs($this->user)->getJson('/api/stocks/portfolio')->json()[0];

        // Net 800 shares @ 900 = 720,000 current value; avg cost = 800
        // Estimated sell: fee = max(20, floor(720000 × 0.001425)) = 1026; tax = floor(720000 × 0.003) = 2160
        // Unrealized = (900-800)×800 − 1026 − 2160 = 80000 − 3186 = 76814
        $this->assertEqualsWithDelta(76814, (float) $position['unrealized_gain'], 1);
        $this->assertEquals('10000.0000', $position['realized_gain']); // (850-800)×200
    }

    public function test_portfolio_excludes_fully_sold_positions(): void
    {
        $stock = Stock::factory()->create(['current_price' => 900.0000]);

        Transaction::factory()->buy($stock, shares: 500, price: 800)->create(['user_id' => $this->user->id]);
        Transaction::factory()->sell($stock, shares: 500, price: 850)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/stocks/portfolio')
            ->assertOk()->assertJsonCount(0);
    }

    public function test_fees_are_included_in_cost_and_deducted_from_revenue(): void
    {
        $stock = Stock::factory()->create(['current_price' => 900.0000]);

        // Buy 1000 @ 800 + 1140 fee → total cost = 801140, avg cost = 801.14
        Transaction::factory()->buy($stock, shares: 1000, price: 800)
            ->create(['user_id' => $this->user->id, 'handling_fee' => 1140, 'transaction_tax' => 0]);

        // Sell 200 @ 900 − 256 fee − 540 tax → net revenue = 180000 - 256 - 540 = 179204
        // realized gain = 179204 − (200 × 801.14) = 179204 − 160228 = 18976
        Transaction::factory()->sell($stock, shares: 200, price: 900)
            ->create(['user_id' => $this->user->id, 'handling_fee' => 256, 'transaction_tax' => 540]);

        $position = $this->actingAs($this->user)->getJson('/api/stocks/portfolio')->json()[0];

        $this->assertEqualsWithDelta(801.14, (float) $position['average_cost'], 0.001);
        $this->assertEqualsWithDelta(18976, (float) $position['realized_gain'], 1);
    }

    public function test_portfolio_shows_multiple_stocks(): void
    {
        $tsmc = Stock::factory()->create(['symbol' => '2330.TW', 'current_price' => 900.0000]);
        $foxconn = Stock::factory()->create(['symbol' => '2317.TW', 'current_price' => 150.0000]);

        Transaction::factory()->buy($tsmc, shares: 100, price: 800)->create(['user_id' => $this->user->id]);
        Transaction::factory()->buy($foxconn, shares: 1000, price: 120)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->getJson('/api/stocks/portfolio')
            ->assertOk()->assertJsonCount(2);
    }
}
