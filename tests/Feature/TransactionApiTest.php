<?php

namespace Tests\Feature;

use App\Jobs\FetchHistoricalPrices;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

        // 1000 shares × 850 = 850,000 trade value
        // handling_fee = max(20, floor(850000 × 0.001425)) = 1211
        // transaction_tax = 0 (buy)
        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
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
                'handling_fee' => '1211.0000',
                'transaction_tax' => '0.0000',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'stock_id' => $stock->id,
            'type' => 'buy',
            'handling_fee' => 1211,
        ]);
    }

    public function test_fees_auto_calculated_for_buy(): void
    {
        $stock = Stock::factory()->create();

        // 100 shares × 500 = 50,000 trade value
        // handling_fee = max(20, floor(50000 × 0.001425)) = max(20, 71) = 71
        // transaction_tax = 0 (buy)
        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 100,
            'price_per_share' => 500,
            'transacted_at' => '2025-01-15',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['handling_fee' => '71.0000', 'transaction_tax' => '0.0000']);
    }

    public function test_fees_auto_calculated_for_sell(): void
    {
        $stock = Stock::factory()->create();
        Transaction::factory()->buy($stock, shares: 1000, price: 800)->create(['user_id' => $this->user->id]);

        // 500 shares × 900 = 450,000 trade value
        // handling_fee = max(20, floor(450000 × 0.001425)) = 641
        // transaction_tax = floor(450000 × 0.003) = 1350
        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'sell',
            'shares' => 500,
            'price_per_share' => 900.00,
            'transacted_at' => '2025-03-01',
        ]);

        $response->assertCreated()->assertJsonFragment([
            'type' => 'sell',
            'handling_fee' => '641.0000',
            'transaction_tax' => '1350.0000',
        ]);
    }

    public function test_fee_discount_applied_to_handling_fee(): void
    {
        $this->user->update(['handling_fee_discount' => 0.4]); // 40% off
        $stock = Stock::factory()->create();

        // 1000 shares × 850 = 850,000; rate = 0.001425 × 0.6 = 0.000855
        // handling_fee = max(20, floor(850000 × 0.000855)) = max(20, 726) = 726
        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 1000,
            'price_per_share' => 850,
            'transacted_at' => '2025-01-15',
        ]);

        $response->assertCreated()->assertJsonFragment(['handling_fee' => '726.0000']);
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

    public function test_dispatches_historical_fetch_job_for_past_date(): void
    {
        Queue::fake();
        $stock = Stock::factory()->create();

        $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 100,
            'price_per_share' => 500,
            'transacted_at' => '2025-01-01',
        ])->assertCreated();

        Queue::assertPushed(FetchHistoricalPrices::class, fn ($job) =>
            $job->stock->id === $stock->id && $job->fromDate === '2025-01-01'
        );
    }

    public function test_does_not_dispatch_historical_fetch_job_for_today(): void
    {
        Queue::fake();
        $stock = Stock::factory()->create();

        $this->actingAs($this->user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 100,
            'price_per_share' => 500,
            'transacted_at' => now()->timezone('Asia/Taipei')->toDateString(),
        ])->assertCreated();

        Queue::assertNotPushed(FetchHistoricalPrices::class);
    }

    public function test_can_delete_own_transaction(): void
    {
        $stock = Stock::factory()->create();
        $tx = Transaction::factory()->buy($stock, shares: 500, price: 100)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson("/api/transactions/{$tx->id}")->assertNoContent();

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }
}
