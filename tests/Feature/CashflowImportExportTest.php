<?php

namespace Tests\Feature;

use App\Models\CashflowRecord;
use App\Models\CashflowSubtype;
use App\Models\CashflowType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CashflowImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CashflowType $type;
    private CashflowSubtype $subtype;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user    = User::factory()->create();
        $this->type    = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Credit Card', 'is_expense' => true]);
        $this->subtype = CashflowSubtype::create(['type_id' => $this->type->id, 'user_id' => $this->user->id, 'name' => 'HSBC']);
        CashflowRecord::create([
            'user_id'     => $this->user->id,
            'type_id'     => $this->type->id,
            'subtype_id'  => $this->subtype->id,
            'amount'      => 5000,
            'note'        => 'Jan bill',
            'recorded_at' => '2024-01-01',
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function test_can_export_cashflow_records_as_csv(): void
    {
        $response = $this->actingAs($this->user)->get('/api/cashflow/export?format=csv');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Credit Card', $content);
        $this->assertStringContainsString('HSBC', $content);
        $this->assertStringContainsString('5000', $content);
    }

    public function test_can_export_cashflow_records_as_json(): void
    {
        $response = $this->actingAs($this->user)->get('/api/cashflow/export?format=json');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('Credit Card', $data[0]['type']);
        $this->assertEquals('HSBC',        $data[0]['subtype']);
        $this->assertEquals('5000.00',     $data[0]['amount']);
    }

    public function test_export_only_returns_own_records(): void
    {
        $other = User::factory()->create();
        $type  = CashflowType::create(['user_id' => $other->id, 'name' => 'Other', 'is_expense' => true]);
        CashflowRecord::create(['user_id' => $other->id, 'type_id' => $type->id, 'amount' => 999, 'recorded_at' => '2024-01-01']);

        $response = $this->actingAs($this->user)->get('/api/cashflow/export?format=json');
        $this->assertCount(1, $response->json());
    }

    public function test_export_csv_includes_example_row_when_empty(): void
    {
        $emptyUser = User::factory()->create();
        $content   = $this->actingAs($emptyUser)->get('/api/cashflow/export?format=csv')->streamedContent();
        $this->assertStringContainsString('example row', $content);
    }

    public function test_export_json_includes_example_row_when_empty(): void
    {
        $emptyUser = User::factory()->create();
        $data      = $this->actingAs($emptyUser)->get('/api/cashflow/export?format=json')->json();
        $this->assertCount(1, $data);
        $this->assertStringContainsString('example row', $data[0]['note']);
    }

    public function test_guest_cannot_export_cashflow(): void
    {
        $this->getJson('/api/cashflow/export?format=csv')->assertUnauthorized();
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function test_can_import_cashflow_records_from_csv(): void
    {
        $content = "year,month,type,subtype,amount,note\n"
                 . "2024,2,Credit Card,HSBC,3000,Feb bill\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/cashflow/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
        $this->assertDatabaseHas('cashflow_records', [
            'user_id' => $this->user->id,
            'type_id' => $this->type->id,
            'amount'  => 3000,
        ]);
    }

    public function test_can_import_cashflow_records_from_json(): void
    {
        $content = json_encode([[
            'year' => 2024, 'month' => 3,
            'type' => 'Credit Card', 'subtype' => 'HSBC',
            'amount' => 4500, 'note' => '',
        ]]);
        $file = UploadedFile::fake()->createWithContent('import.json', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/cashflow/import', ['file' => $file, 'format' => 'json']);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
    }

    public function test_import_skips_records_with_unknown_type(): void
    {
        $content = "year,month,type,subtype,amount,note\n"
                 . "2024,2,Unknown Type,,1000,\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/cashflow/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 0]);
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_import_handles_empty_subtype(): void
    {
        $typeNoSub = CashflowType::create(['user_id' => $this->user->id, 'name' => 'Income', 'is_expense' => false]);

        $content = "year,month,type,subtype,amount,note\n"
                 . "2024,2,Income,,8000,salary\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->user)
                         ->postJson('/api/cashflow/import', ['file' => $file, 'format' => 'csv']);

        $response->assertOk()->assertJsonFragment(['imported' => 1]);
        $this->assertDatabaseHas('cashflow_records', ['type_id' => $typeNoSub->id, 'subtype_id' => null]);
    }

    public function test_guest_cannot_import_cashflow(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', '');
        $this->postJson('/api/cashflow/import', ['file' => $file])->assertUnauthorized();
    }
}
