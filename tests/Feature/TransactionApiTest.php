<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_a_buy_transaction(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW']);

        $response = $this->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 1000,
            'price_per_share' => 850.00,
            'transacted_at' => '2025-01-15',
            'notes' => 'Initial position',
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'type' => 'buy',
                'shares' => '1000.0000',
                'price_per_share' => '850.0000',
            ]);

        $this->assertDatabaseHas('transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 1000,
        ]);
    }

    public function test_can_record_a_sell_transaction(): void
    {
        $stock = Stock::factory()->create();
        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create();

        $response = $this->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'sell',
            'shares' => 500,
            'price_per_share' => 900.00,
            'transacted_at' => '2025-03-01',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['type' => 'sell']);
    }

    public function test_cannot_sell_more_shares_than_owned(): void
    {
        $stock = Stock::factory()->create();
        Transaction::factory()->buy($stock, shares: 100, price: 800)->create();

        $response = $this->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'sell',
            'shares' => 200,
            'price_per_share' => 900.00,
            'transacted_at' => '2025-03-01',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['shares']);
    }

    public function test_transaction_requires_valid_fields(): void
    {
        $response = $this->postJson('/api/transactions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_id', 'type', 'shares', 'price_per_share', 'transacted_at']);
    }

    public function test_type_must_be_buy_or_sell(): void
    {
        $stock = Stock::factory()->create();

        $response = $this->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'hold',
            'shares' => 100,
            'price_per_share' => 100,
            'transacted_at' => '2025-01-01',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_can_list_transactions_for_a_stock(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW']);
        $other = Stock::factory()->create(['symbol' => '2317.TW']);

        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create();
        Transaction::factory()->sell($stock, shares: 200, price: 900)->create();
        Transaction::factory()->buy($other, shares: 500, price: 100)->create();

        $response = $this->getJson("/api/stocks/2330.TW/transactions");

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_can_delete_a_transaction(): void
    {
        $stock = Stock::factory()->create();
        $tx = Transaction::factory()->buy($stock, shares: 500, price: 100)->create();

        $this->deleteJson("/api/transactions/{$tx->id}")->assertNoContent();

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }
}
