<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_record_a_buy_transaction(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW']);

        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 1000,
            'price_per_share' => 850.00,
            'handling_fee' => 1212,
            'transacted_at' => '2025-01-15',
            'notes' => 'Initial position',
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'type' => 'buy',
                'shares' => '1000.0000',
                'price_per_share' => '850.0000',
                'handling_fee' => '1212.0000',
                'transaction_tax' => '0.0000',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'stock_id' => $stock->id,
            'type' => 'buy',
            'handling_fee' => 1212,
        ]);
    }

    public function test_fees_default_to_zero_when_omitted(): void
    {
        $stock = Stock::factory()->create();

        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 100,
            'price_per_share' => 500,
            'transacted_at' => '2025-01-15',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['handling_fee' => '0.0000', 'transaction_tax' => '0.0000']);
    }

    public function test_fees_must_be_non_negative(): void
    {
        $stock = Stock::factory()->create();

        $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 100,
            'price_per_share' => 500,
            'handling_fee' => -1,
            'transacted_at' => '2025-01-15',
        ])->assertUnprocessable()->assertJsonValidationErrors(['handling_fee']);
    }

    public function test_can_record_a_sell_transaction(): void
    {
        $stock = Stock::factory()->create();
        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'sell',
            'shares' => 500,
            'price_per_share' => 900.00,
            'transacted_at' => '2025-03-01',
        ]);

        $response->assertCreated()->assertJsonFragment(['type' => 'sell']);
    }

    public function test_cannot_sell_more_shares_than_owned(): void
    {
        $stock = Stock::factory()->create();
        Transaction::factory()->buy($stock, shares: 100, price: 800)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'sell',
            'shares' => 200,
            'price_per_share' => 900.00,
            'transacted_at' => '2025-03-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['shares']);
    }

    public function test_transaction_requires_valid_fields(): void
    {
        $this->actingAs($this->user)->postJson('/api/transactions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_id', 'type', 'shares', 'price_per_share', 'transacted_at']);
    }

    public function test_type_must_be_buy_or_sell(): void
    {
        $stock = Stock::factory()->create();

        $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'hold',
            'shares' => 100,
            'price_per_share' => 100,
            'transacted_at' => '2025-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_can_list_own_transactions_for_a_stock(): void
    {
        $stock = Stock::factory()->create(['symbol' => '2330.TW']);
        $other = Stock::factory()->create(['symbol' => '2317.TW']);

        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create(['user_id' => $this->user->id]);
        Transaction::factory()->sell($stock, shares: 200, price: 900)->create(['user_id' => $this->user->id]);
        Transaction::factory()->buy($other, shares: 500, price: 100)->create(['user_id' => $this->user->id]);
        // Another user's transaction on the same stock — should not appear
        Transaction::factory()->buy($stock, shares: 300, price: 790)->create();

        $response = $this->actingAs($this->user)->getJson('/api/stocks/2330.TW/transactions');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_can_delete_own_transaction(): void
    {
        $stock = Stock::factory()->create();
        $tx = Transaction::factory()->buy($stock, shares: 500, price: 100)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/transactions/{$tx->id}")->assertNoContent();

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }
}
