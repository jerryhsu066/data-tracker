<?php

namespace Tests\Feature;

use App\Jobs\FetchStockPrice;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StockApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_tracked_stocks_without_auth(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'current_price' => 150.00]);
        Stock::factory()->create(['symbol' => 'TSLA', 'name' => 'Tesla Inc.', 'current_price' => 200.00]);

        $this->getJson('/api/stocks')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['symbol' => 'AAPL'])
            ->assertJsonFragment(['symbol' => 'TSLA']);
    }

    public function test_can_add_a_stock_to_track(): void
    {
        $this->actingAs($this->user)->postJson('/api/stocks', [
            'symbol' => 'AAPL',
            'name' => 'Apple Inc.',
        ])->assertCreated()->assertJsonFragment(['symbol' => 'AAPL']);

        $this->assertDatabaseHas('stocks', ['symbol' => 'AAPL']);
    }

    public function test_symbol_is_stored_uppercase(): void
    {
        $this->actingAs($this->user)->postJson('/api/stocks', [
            'symbol' => 'aapl',
            'name' => 'Apple Inc.',
        ])->assertCreated();

        $this->assertDatabaseHas('stocks', ['symbol' => 'AAPL']);
    }

    public function test_cannot_add_duplicate_stock_symbol(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL']);

        $this->actingAs($this->user)->postJson('/api/stocks', [
            'symbol' => 'AAPL',
            'name' => 'Apple Inc.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['symbol']);
    }

    public function test_symbol_and_name_are_required(): void
    {
        $this->actingAs($this->user)->postJson('/api/stocks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol', 'name']);
    }

    public function test_can_show_a_stock_by_symbol_without_auth(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'current_price' => 150.00]);

        $this->getJson('/api/stocks/AAPL')
            ->assertOk()
            ->assertJsonFragment(['symbol' => 'AAPL', 'name' => 'Apple Inc.']);
    }

    public function test_show_returns_404_for_unknown_symbol(): void
    {
        $this->getJson('/api/stocks/FAKE')->assertNotFound();
    }

    public function test_can_delete_a_tracked_stock(): void
    {
        Stock::factory()->create(['symbol' => 'AAPL']);

        $this->actingAs($this->user)->deleteJson('/api/stocks/AAPL')->assertNoContent();

        $this->assertDatabaseMissing('stocks', ['symbol' => 'AAPL']);
    }

    public function test_fetch_endpoint_dispatches_fetch_stock_price_job(): void
    {
        Queue::fake();
        Stock::factory()->create(['symbol' => 'AAPL']);

        $this->actingAs($this->user)->postJson('/api/stocks/AAPL/fetch')->assertAccepted();

        Queue::assertPushed(FetchStockPrice::class, fn ($job) => $job->stock->symbol === 'AAPL');
    }
}
