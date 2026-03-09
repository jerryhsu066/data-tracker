<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserScopedTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_transaction(): void
    {
        $stock = Stock::factory()->create();

        $this->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 100,
            'price_per_share' => 800,
            'transacted_at' => '2025-01-01',
        ])->assertUnauthorized();
    }

    public function test_transaction_is_associated_with_authenticated_user(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create();

        $this->actingAs($user)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'buy',
            'shares' => 100,
            'price_per_share' => 800,
            'transacted_at' => '2025-01-01',
        ])->assertCreated();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'stock_id' => $stock->id,
        ]);
    }

    public function test_user_can_only_see_own_transactions(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stock = Stock::factory()->create(['symbol' => '2330.TW']);

        Transaction::factory()->buy($stock, shares: 500, price: 800)->create(['user_id' => $alice->id]);
        Transaction::factory()->buy($stock, shares: 1000, price: 810)->create(['user_id' => $bob->id]);

        $response = $this->actingAs($alice)->getJson('/api/stocks/2330.TW/transactions');

        $response->assertOk()->assertJsonCount(1);
        $this->assertEquals($alice->id, $response->json()[0]['user_id']);
    }

    public function test_user_cannot_delete_another_users_transaction(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stock = Stock::factory()->create();

        $bobsTx = Transaction::factory()->buy($stock, shares: 100, price: 800)->create(['user_id' => $bob->id]);

        $this->actingAs($alice)->deleteJson("/api/transactions/{$bobsTx->id}")
            ->assertForbidden();
    }

    public function test_user_can_delete_own_transaction(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create();

        $tx = Transaction::factory()->buy($stock, shares: 100, price: 800)->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/transactions/{$tx->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }

    public function test_sell_validation_only_counts_users_own_shares(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stock = Stock::factory()->create();

        // Bob has 500 shares but Alice has 0
        Transaction::factory()->buy($stock, shares: 500, price: 800)->create(['user_id' => $bob->id]);

        $this->actingAs($alice)->postJson('/api/transactions', [
            'stock_id' => $stock->id,
            'type' => 'sell',
            'shares' => 100,
            'price_per_share' => 900,
            'transacted_at' => '2025-03-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['shares']);
    }

    public function test_portfolio_only_shows_authenticated_users_positions(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stock = Stock::factory()->create(['current_price' => 900]);

        Transaction::factory()->buy($stock, shares: 100, price: 800)->create(['user_id' => $alice->id]);
        Transaction::factory()->buy($stock, shares: 500, price: 700)->create(['user_id' => $bob->id]);

        $response = $this->actingAs($alice)->getJson('/api/portfolio');

        $response->assertOk()->assertJsonCount(1);
        $this->assertEquals('100.0000', $response->json()[0]['net_shares']);
    }

    public function test_unauthenticated_user_cannot_access_portfolio(): void
    {
        $this->getJson('/api/portfolio')->assertUnauthorized();
    }

    public function test_stock_write_operations_require_auth(): void
    {
        $this->postJson('/api/stocks', ['symbol' => 'AAPL', 'name' => 'Apple'])->assertUnauthorized();

        $stock = Stock::factory()->create(['symbol' => 'AAPL']);
        $this->deleteJson('/api/stocks/AAPL')->assertUnauthorized();
        $this->postJson('/api/stocks/AAPL/fetch')->assertUnauthorized();
    }
}
