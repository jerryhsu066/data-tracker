<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StockImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $this->stock = Stock::create(['symbol' => '2330', 'name' => 'TSMC']);
        Transaction::create([
            'user_id'          => $this->user->id,
            'stock_id'         => $this->stock->id,
            'type'             => 'buy',
            'shares'           => 1000,
            'price_per_share'  => 550,
            'handling_fee'     => 20,
            'transaction_tax'  => 0,
            'transacted_at'    => '2024-01-15',
            'notes'            => 'first buy',
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function test_can_export_transactions_as_csv(): void
    {
        $response = $this->actingAs($this->user)->get('/api/stocks/export?format=csv');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('2330', $content);
        $this->assertStringContainsString('buy', $content);
        $this->assertStringContainsString('first buy', $content);
    }

    public function test_can_export_transactions_as_json(): void
    {
        $response = $this->actingAs($this->user)->get('/api/stocks/export?format=json');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('2330', $data[0]['symbol']);
        $this->assertEquals('buy',  $data[0]['type']);
    }

    public function test_export_only_returns_own_transactions(): void
    {
        $other = User::factory()->create();
        Transaction::create([
            'user_id' => $other->id, 'stock_id' => $this->stock->id,
            'type' => 'buy', 'shares' => 500, 'price_per_share' => 600,
            'handling_fee' => 20, 'transaction_tax' => 0, 'transacted_at' => '2024-02-01',
        ]);

        $response = $this->actingAs($this->user)->get('/api/stocks/export?format=json');
        $this->assertCount(1, $response->json());
    }

    public function test_export_csv_includes_example_row_when_empty(): void
    {
        $emptyUser = User::factory()->create();
        $content   = $this->actingAs($emptyUser)->get('/api/stocks/export?format=csv')->streamedContent();
        $this->assertStringContainsString('example row', $content);
    }

    public function test_export_json_includes_example_row_when_empty(): void
    {
        $emptyUser = User::factory()->create();
        $data      = $this->actingAs($emptyUser)->get('/api/stocks/export?format=json')->json();
        $this->assertCount(1, $data);
        $this->assertStringContainsString('example row', $data[0]['notes']);
    }

    public function test_guest_cannot_export(): void
    {
        $this->getJson('/api/stocks/export?format=csv')->assertUnauthorized();
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function test_can_import_transactions_from_csv(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,2330,sell,200,600,20,540,March sell\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'type'    => 'sell',
            'shares'  => 200,
        ]);
    }

    public function test_can_import_transactions_from_json(): void
    {
        $content = json_encode([[
            'date'             => '2024-03-01',
            'symbol'           => '2330',
            'type'             => 'sell',
            'shares'           => 300,
            'price_per_share'  => 620,
            'handling_fee'     => 20,
            'transaction_tax'  => 558,
            'notes'            => '',
        ]]);
        $file = UploadedFile::fake()->createWithContent('import.json', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'json']);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
    }

    public function test_import_creates_stock_if_not_found(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,0050,buy,500,150,20,0,\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $this->actingAs($this->user)
             ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv'])
             ->assertOk()->assertJsonFragment(['imported' => 1]);

        $this->assertDatabaseHas('stocks', ['symbol' => '0050']);
    }

    public function test_import_skips_rows_with_invalid_data(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "not-a-date,2330,buy,abc,550,20,0,\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 0]);
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_guest_cannot_import(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', '');
        $this->postJson('/api/stocks/import', ['file' => $file])->assertUnauthorized();
    }
}
