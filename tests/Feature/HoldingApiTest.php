<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_holdings_with_current_value(): void
    {
        $stock = Stock::factory()->create(['symbol' => 'AAPL', 'current_price' => 150.0000]);
        Holding::factory()->create(['stock_id' => $stock->id, 'shares' => 10, 'average_cost' => 120.0000]);

        $response = $this->getJson('/api/holdings');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'shares' => '10.0000',
                'average_cost' => '120.0000',
                'current_value' => '1500.0000',
                'gain_loss' => '300.0000',
            ]);
    }

    public function test_can_add_a_holding(): void
    {
        $stock = Stock::factory()->create(['symbol' => 'AAPL']);

        $response = $this->postJson('/api/holdings', [
            'stock_id' => $stock->id,
            'shares' => 5,
            'average_cost' => 100.00,
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['shares' => '5.0000']);

        $this->assertDatabaseHas('holdings', ['stock_id' => $stock->id, 'shares' => 5]);
    }

    public function test_holding_requires_valid_stock_and_positive_values(): void
    {
        $response = $this->postJson('/api/holdings', [
            'stock_id' => 999,
            'shares' => -1,
            'average_cost' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_id', 'shares', 'average_cost']);
    }

    public function test_can_update_a_holding(): void
    {
        $stock = Stock::factory()->create();
        $holding = Holding::factory()->create(['stock_id' => $stock->id, 'shares' => 5]);

        $response = $this->putJson("/api/holdings/{$holding->id}", [
            'shares' => 10,
            'average_cost' => 110.00,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['shares' => '10.0000']);
    }

    public function test_can_delete_a_holding(): void
    {
        $stock = Stock::factory()->create();
        $holding = Holding::factory()->create(['stock_id' => $stock->id]);

        $this->deleteJson("/api/holdings/{$holding->id}")->assertNoContent();

        $this->assertDatabaseMissing('holdings', ['id' => $holding->id]);
    }

    public function test_portfolio_summary_is_correct_with_multiple_holdings(): void
    {
        $apple = Stock::factory()->create(['symbol' => 'AAPL', 'current_price' => 200.0000]);
        $tesla = Stock::factory()->create(['symbol' => 'TSLA', 'current_price' => 300.0000]);

        Holding::factory()->create(['stock_id' => $apple->id, 'shares' => 10, 'average_cost' => 150.0000]);
        Holding::factory()->create(['stock_id' => $tesla->id, 'shares' => 5, 'average_cost' => 250.0000]);

        $response = $this->getJson('/api/holdings');

        $response->assertOk()->assertJsonCount(2);

        $items = $response->json();

        $appleHolding = collect($items)->firstWhere('stock.symbol', 'AAPL');
        $this->assertEquals('2000.0000', $appleHolding['current_value']); // 10 * 200
        $this->assertEquals('500.0000', $appleHolding['gain_loss']);       // (200-150) * 10

        $teslaHolding = collect($items)->firstWhere('stock.symbol', 'TSLA');
        $this->assertEquals('1500.0000', $teslaHolding['current_value']); // 5 * 300
        $this->assertEquals('250.0000', $teslaHolding['gain_loss']);       // (300-250) * 5
    }
}
