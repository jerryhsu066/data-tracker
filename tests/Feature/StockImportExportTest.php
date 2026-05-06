<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\StockSplit;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
        StockTransaction::create([
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
        StockTransaction::create([
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

    // ── Preview ───────────────────────────────────────────────────────────────

    public function test_preview_returns_valid_invalid_and_duplicate_counts(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,2330,sell,200,600,20,540,March sell\n"   // valid
                 . "not-a-date,2330,buy,abc,550,20,0,\n";               // invalid
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import/preview', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()
                 ->assertJsonFragment(['total' => 2, 'valid' => 1]);
        $this->assertCount(1, $response->json('invalid'));
        $this->assertCount(0, $response->json('duplicates'));
    }

    public function test_preview_detects_duplicate_transactions(): void
    {
        // setUp created: 2330 buy 1000 shares @ 550 on 2024-01-15
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-01-15,2330,buy,1000,550,20,0,first buy\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import/preview', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['valid' => 0]);
        $this->assertCount(1, $response->json('duplicates'));
        $this->assertCount(0, $response->json('invalid'));
    }

    public function test_guest_cannot_preview(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', '');
        $this->postJson('/api/stocks/import/preview', ['file' => $file])->assertUnauthorized();
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
        $this->assertDatabaseHas('stock_transactions', [
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
        Http::fake([
            '*finance.yahoo.com*' => Http::response([
                'chart' => ['result' => [['meta' => [
                    'regularMarketPrice' => 150.0,
                    'chartPreviousClose' => 148.0,
                ]]]],
            ]),
        ]);

        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,0050,buy,500,150,20,0,\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $this->actingAs($this->user)
             ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv'])
             ->assertOk()->assertJsonFragment(['imported' => 1]);

        $this->assertDatabaseHas('stocks', ['symbol' => '0050']);
    }

    public function test_import_skips_row_if_new_stock_not_on_yahoo(): void
    {
        Http::fake([
            '*finance.yahoo.com*' => Http::response(['chart' => ['result' => null]]),
        ]);

        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,FAKEXYZ,buy,500,150,20,0,\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
             ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv'])
             ->assertOk()->assertJsonFragment(['imported' => 0]);

        $this->assertCount(1, $response->json('skipped'));
        $this->assertDatabaseMissing('stocks', ['symbol' => 'FAKEXYZ']);
    }

    public function test_preview_marks_new_untrackable_symbols_as_invalid(): void
    {
        Http::fake([
            '*finance.yahoo.com*' => Http::response(['chart' => ['result' => null]]),
        ]);

        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,FAKEXYZ,buy,500,150,20,0,\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
             ->postJson('/api/stocks/import/preview', ['file' => $file, 'format' => 'csv'])
             ->assertOk()->assertJsonFragment(['valid' => 0]);

        $this->assertCount(1, $response->json('invalid'));
        $this->assertDatabaseMissing('stocks', ['symbol' => 'FAKEXYZ']);
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

    public function test_import_skips_duplicates_by_default(): void
    {
        // setUp created: 2330 buy 1000 shares @ 550 on 2024-01-15
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-01-15,2330,buy,1000,550,20,0,first buy\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 0]);
        $this->assertDatabaseCount('stock_transactions', 1);
    }

    public function test_import_includes_duplicates_when_not_skipping(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-01-15,2330,buy,1000,550,20,0,first buy\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv', 'skip_duplicates' => false]);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
        $this->assertDatabaseCount('stock_transactions', 2);
    }

    public function test_malformed_csv_row_is_reported_not_silently_dropped(): void
    {
        // Row 3 has too few columns
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,2330,sell,200,600,20,540,ok\n"
                 . "2024-03-02,2330\n";   // only 2 columns
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import/preview', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['total' => 2, 'valid' => 1]);
        $this->assertCount(1, $response->json('invalid'));
        $this->assertStringContainsString('column', $response->json('invalid.0.reason'));
    }

    public function test_invalid_json_file_is_reported(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.json', 'not json at all');

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import/preview', ['file' => $file, 'format' => 'json']);

        $response->assertOk()->assertJsonFragment(['total' => 1]);
        $this->assertCount(1, $response->json('invalid'));
    }

    public function test_import_stores_null_for_blank_notes(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,2330,sell,200,600,20,540,   \n";   // notes = spaces only
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $this->actingAs($this->user)
             ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv'])
             ->assertOk()->assertJsonFragment(['imported' => 1]);

        $this->assertDatabaseHas('stock_transactions', ['notes' => null]);
    }

    public function test_guest_cannot_import(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', '');
        $this->postJson('/api/stocks/import', ['file' => $file])->assertUnauthorized();
    }

    // ── Split rows in export ──────────────────────────────────────────────────

    public function test_export_csv_includes_split_rows(): void
    {
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2024-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);

        $content = $this->actingAs($this->user)->get('/api/stocks/export?format=csv')->streamedContent();

        $this->assertStringContainsString('ratio_from', $content);
        $this->assertStringContainsString('ratio_to', $content);
        $this->assertStringContainsString('split', $content);
        $this->assertStringContainsString('2024-06-01', $content);
    }

    public function test_export_json_includes_split_rows(): void
    {
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2024-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);

        $data = $this->actingAs($this->user)->get('/api/stocks/export?format=json')->json();

        $splitRow = collect($data)->firstWhere('type', 'split');
        $this->assertNotNull($splitRow);
        $this->assertEquals('2024-06-01', $splitRow['date']);
        $this->assertEquals('2330', $splitRow['symbol']);
        $this->assertEquals(1, $splitRow['ratio_from']);
        $this->assertEquals(2, $splitRow['ratio_to']);
    }

    public function test_export_rows_are_sorted_chronologically(): void
    {
        // Buy on Jan 15, split on Jun 1 — split should appear after the buy in export
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2024-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);

        $data = $this->actingAs($this->user)->get('/api/stocks/export?format=json')->json();

        $this->assertEquals('buy',   $data[0]['type']);
        $this->assertEquals('split', $data[1]['type']);
    }

    // ── Split rows in import ──────────────────────────────────────────────────

    public function test_import_creates_splits_from_split_rows(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes,ratio_from,ratio_to\n"
                 . "2024-06-01,2330,split,,,,,,1,2\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
        $this->assertDatabaseHas('stock_splits', [
            'stock_id'   => $this->stock->id,
            'split_date' => '2024-06-01',
            'ratio_from' => 1,
            'ratio_to'   => 2,
        ]);
    }

    public function test_import_skips_duplicate_splits(): void
    {
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2024-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);

        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes,ratio_from,ratio_to\n"
                 . "2024-06-01,2330,split,,,,,,1,2\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 0]);
        $this->assertDatabaseCount('stock_splits', 1);
    }

    public function test_preview_counts_split_rows(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes,ratio_from,ratio_to\n"
                 . "2024-06-01,2330,split,,,,,,1,2\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import/preview', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['total' => 1, 'valid' => 1]);
        $this->assertCount(0, $response->json('invalid'));
    }

    public function test_preview_detects_duplicate_split(): void
    {
        StockSplit::create(['stock_id' => $this->stock->id, 'split_date' => '2024-06-01', 'ratio_from' => 1, 'ratio_to' => 2]);

        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes,ratio_from,ratio_to\n"
                 . "2024-06-01,2330,split,,,,,,1,2\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import/preview', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['valid' => 0]);
        $this->assertCount(1, $response->json('duplicates'));
    }

    public function test_import_skips_split_for_unknown_stock(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes,ratio_from,ratio_to\n"
                 . "2024-06-01,UNKNOWN,split,,,,,,1,2\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 0]);
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_import_handles_mixed_transactions_and_splits(): void
    {
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes,ratio_from,ratio_to\n"
                 . "2024-03-01,2330,sell,200,600,20,540,sell some,,\n"
                 . "2024-06-01,2330,split,,,,,,1,2\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 2]);
        $this->assertDatabaseHas('stock_splits', ['split_date' => '2024-06-01']);
        $this->assertDatabaseHas('stock_transactions', ['type' => 'sell', 'shares' => 200]);
    }

    public function test_old_csv_without_ratio_columns_still_imports(): void
    {
        // 8-column CSV (no ratio_from / ratio_to) should still import as before
        $content = "date,symbol,type,shares,price_per_share,handling_fee,transaction_tax,notes\n"
                 . "2024-03-01,2330,sell,200,600,20,540,old format\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/stocks/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
    }
}
