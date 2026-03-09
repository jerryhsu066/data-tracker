<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_shows_aggregated_position_per_stock(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW', 'current_price' => 900.0000]);

        // Buy 1000 @ 800, buy 500 @ 820 → WAC = (800000+410000)/1500 = 806.67
        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create();
        Transaction::factory()->buy($stock, shares: 500, price: 820)->create();

        $response = $this->getJson('/api/portfolio');

        $response->assertOk()->assertJsonCount(1);

        $position = $response->json()[0];

        $this->assertEquals('2330.TW', $position['stock']['symbol']);
        $this->assertEquals('1500.0000', $position['net_shares']);
        $this->assertEqualsWithDelta(806.6667, (float) $position['average_cost'], 0.001);
        $this->assertEquals('1350000.0000', $position['current_value']); // 1500 * 900
    }

    public function test_portfolio_deducts_sold_shares(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW', 'current_price' => 900.0000]);

        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create();
        Transaction::factory()->sell($stock, shares: 300, price: 850)->create();

        $response = $this->getJson('/api/portfolio');

        $position = $response->json()[0];

        $this->assertEquals('700.0000', $position['net_shares']); // 1000 - 300
        $this->assertEquals('800.0000', $position['average_cost']); // WAC unchanged by sell
    }

    public function test_portfolio_calculates_unrealized_and_realized_gain(): void
    {
        $stock = Stock::factory()->create(['current_price' => 900.0000]);

        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create();
        Transaction::factory()->sell($stock, shares: 200, price: 850)->create();

        $response = $this->getJson('/api/portfolio');

        $position = $response->json()[0];

        // unrealized: (900 - 800) * 800 remaining shares = 80000
        $this->assertEquals('80000.0000', $position['unrealized_gain']);

        // realized: (850 - 800) * 200 sold shares = 10000
        $this->assertEquals('10000.0000', $position['realized_gain']);
    }

    public function test_portfolio_excludes_fully_sold_positions(): void
    {
        $stock = Stock::factory()->create(['current_price' => 900.0000]);

        Transaction::factory()->buy($stock, shares: 500, price: 800)->create();
        Transaction::factory()->sell($stock, shares: 500, price: 850)->create();

        $response = $this->getJson('/api/portfolio');

        $response->assertOk()->assertJsonCount(0);
    }

    public function test_portfolio_shows_multiple_stocks(): void
    {
        $tsmc = Stock::factory()->create(['symbol' => '2330.TW', 'current_price' => 900.0000]);
        $foxconn = Stock::factory()->create(['symbol' => '2317.TW', 'current_price' => 150.0000]);

        Transaction::factory()->buy($tsmc, shares: 100, price: 800)->create();
        Transaction::factory()->buy($foxconn, shares: 1000, price: 120)->create();

        $response = $this->getJson('/api/portfolio');

        $response->assertOk()->assertJsonCount(2);
    }
}
