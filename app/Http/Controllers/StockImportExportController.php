<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockSplit;
use App\Models\StockTransaction;
use App\Services\StockPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockImportExportController extends Controller
{
    use Concerns\ParsesImportFile;

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->get('format', 'csv');
        $userId = $request->user()->id;

        $transactions = StockTransaction::with('stock')
            ->where('user_id', $userId)
            ->orderBy('transacted_at')
            ->get();

        $example = [
            'date'            => '2024-01-15',
            'symbol'          => '2330',
            'type'            => 'buy',
            'shares'          => 1000,
            'price_per_share' => 550,
            'handling_fee'    => 20,
            'transaction_tax' => 0,
            'notes'           => 'example row — delete before importing',
            'ratio_from'      => null,
            'ratio_to'        => null,
        ];

        if ($transactions->isEmpty()) {
            if ($format === 'json') {
                return response()->json([$example])
                    ->header('Content-Disposition', 'attachment; filename="transactions.json"');
            }

            return response()->stream(function () use ($example) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, array_keys($example));
                fputcsv($handle, array_values($example));
                fclose($handle);
            }, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="transactions.csv"',
            ]);
        }

        // Load splits for all stocks this user has transactions for
        $stockIds = $transactions->pluck('stock_id')->unique();
        $splits   = StockSplit::whereIn('stock_id', $stockIds)
            ->with('stock')
            ->orderBy('split_date')
            ->get();

        // Merge transactions and splits into a single chronological list
        $rows = collect();

        foreach ($transactions as $tx) {
            $rows->push([
                'date'            => $tx->transacted_at->format('Y-m-d'),
                'symbol'          => $tx->stock->symbol,
                'type'            => $tx->type,
                'shares'          => $tx->shares,
                'price_per_share' => $tx->price_per_share,
                'handling_fee'    => $tx->handling_fee,
                'transaction_tax' => $tx->transaction_tax,
                'notes'           => $tx->notes ?? '',
                'ratio_from'      => null,
                'ratio_to'        => null,
            ]);
        }

        foreach ($splits as $split) {
            $rows->push([
                'date'            => $split->split_date->format('Y-m-d'),
                'symbol'          => $split->stock->symbol,
                'type'            => 'split',
                'shares'          => null,
                'price_per_share' => null,
                'handling_fee'    => null,
                'transaction_tax' => null,
                'notes'           => null,
                'ratio_from'      => $split->ratio_from,
                'ratio_to'        => $split->ratio_to,
            ]);
        }

        $rows = $rows->sortBy('date')->values();

        if ($format === 'json') {
            return response()->json($rows)
                ->header('Content-Disposition', 'attachment; filename="transactions.json"');
        }

        return response()->stream(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'symbol', 'type', 'shares', 'price_per_share', 'handling_fee', 'transaction_tax', 'notes', 'ratio_from', 'ratio_to']);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="transactions.csv"',
        ]);
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function preview(Request $request, StockPriceService $service): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file'],
            'format' => ['sometimes', 'in:csv,json'],
        ]);

        $format = $request->get('format', 'csv');
        $userId = $request->user()->id;
        $rows   = $this->parseFile($request->file('file'), $format);

        $invalid     = [];
        $duplicates  = [];
        $valid       = 0;
        $symbolCache = [];

        foreach ($rows as $row) {
            $rowNum = $row['_row'];

            if (isset($row['_parse_error'])) {
                $invalid[] = ['row' => $rowNum, 'reason' => $row['_parse_error']];
                continue;
            }

            unset($row['_row']);
            $row = array_map('trim', $row);

            if (($row['type'] ?? '') === 'split') {
                $result = $this->previewSplitRow($row, $rowNum);
                if ($result === 'invalid') {
                    $invalid[] = ['row' => $rowNum, 'reason' => 'Invalid or missing split data'];
                } elseif ($result === 'duplicate') {
                    $duplicates[] = ['row' => $rowNum, 'label' => ($row['symbol'] ?? '') . " split on {$row['date']}"];
                } else {
                    $valid++;
                }
                continue;
            }

            if (empty($row['date']) || empty($row['symbol']) || empty($row['type'])
                || ! is_numeric($row['shares'] ?? null)
                || ! is_numeric($row['price_per_share'] ?? null)
                || ! strtotime($row['date'])) {
                $invalid[] = ['row' => $rowNum, 'reason' => 'Invalid or missing data'];
                continue;
            }

            $symbol = strtoupper($row['symbol']);
            $stock  = Stock::where('symbol', $symbol)->first();

            if (! $stock) {
                if (! array_key_exists($symbol, $symbolCache)) {
                    $symbolCache[$symbol] = $service->checkSymbol($symbol);
                }
                if (! $symbolCache[$symbol]) {
                    $invalid[] = ['row' => $rowNum, 'reason' => 'Symbol not found on Yahoo Finance.'];
                    continue;
                }
            }

            if ($stock && StockTransaction::where('user_id', $userId)
                    ->where('stock_id', $stock->id)
                    ->where('type', $row['type'])
                    ->whereDate('transacted_at', $row['date'])
                    ->where('shares', (int) $row['shares'])
                    ->where('price_per_share', (float) $row['price_per_share'])
                    ->exists()) {
                $duplicates[] = ['row' => $rowNum, 'label' => $symbol . " {$row['type']} on {$row['date']}"];
                continue;
            }

            $valid++;
        }

        return response()->json([
            'total'      => count($rows),
            'valid'      => $valid,
            'invalid'    => $invalid,
            'duplicates' => $duplicates,
        ]);
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function import(Request $request, StockPriceService $service): JsonResponse
    {
        $request->validate([
            'file'            => ['required', 'file'],
            'format'          => ['sometimes', 'in:csv,json'],
            'skip_duplicates' => ['sometimes', 'boolean'],
        ]);

        $format         = $request->get('format', 'csv');
        $userId         = $request->user()->id;
        $skipDuplicates = $request->boolean('skip_duplicates', true);
        $rows           = $this->parseFile($request->file('file'), $format);

        $imported    = 0;
        $skipped     = [];
        $symbolCache = [];

        foreach ($rows as $row) {
            $rowNum = $row['_row'];

            if (isset($row['_parse_error'])) {
                $skipped[] = ['row' => $rowNum, 'reason' => $row['_parse_error']];
                continue;
            }

            unset($row['_row']);
            $row = array_map('trim', $row);

            if (($row['type'] ?? '') === 'split') {
                $result = $this->importSplitRow($row, $rowNum, $skipDuplicates);
                if ($result === true) {
                    $imported++;
                } elseif (is_array($result)) {
                    $skipped[] = $result;
                }
                continue;
            }

            if (empty($row['date']) || empty($row['symbol']) || empty($row['type'])
                || ! is_numeric($row['shares'] ?? null)
                || ! is_numeric($row['price_per_share'] ?? null)
                || ! strtotime($row['date'])) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Invalid or missing data'];
                continue;
            }

            $symbol = strtoupper($row['symbol']);

            if (! array_key_exists($symbol, $symbolCache)) {
                $existing = Stock::where('symbol', $symbol)->first();
                if ($existing) {
                    $symbolCache[$symbol] = $existing;
                } else {
                    $newStock = Stock::create(['symbol' => $symbol, 'name' => $symbol]);
                    if ($service->updatePrice($newStock)) {
                        $symbolCache[$symbol] = $newStock;
                    } else {
                        $newStock->forceDelete();
                        $symbolCache[$symbol] = false;
                    }
                }
            }

            if ($symbolCache[$symbol] === false) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Symbol not found on Yahoo Finance.'];
                continue;
            }

            $stock = $symbolCache[$symbol];

            if ($skipDuplicates && StockTransaction::where('user_id', $userId)
                    ->where('stock_id', $stock->id)
                    ->where('type', $row['type'])
                    ->whereDate('transacted_at', $row['date'])
                    ->where('shares', (int) $row['shares'])
                    ->where('price_per_share', (float) $row['price_per_share'])
                    ->exists()) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Duplicate'];
                continue;
            }

            StockTransaction::create([
                'user_id'         => $userId,
                'stock_id'        => $stock->id,
                'type'            => $row['type'],
                'shares'          => $row['shares'],
                'price_per_share' => $row['price_per_share'],
                'handling_fee'    => $row['handling_fee'] ?? 0,
                'transaction_tax' => $row['transaction_tax'] ?? 0,
                'transacted_at'   => $row['date'],
                'notes'           => ($row['notes'] ?? '') === '' ? null : $row['notes'],
            ]);

            $imported++;
        }

        return response()->json(['imported' => $imported, 'skipped' => $skipped]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function previewSplitRow(array $row, int $rowNum): string
    {
        if (empty($row['date']) || empty($row['symbol'])
            || ! is_numeric($row['ratio_from'] ?? null) || (int) ($row['ratio_from'] ?? 0) < 1
            || ! is_numeric($row['ratio_to'] ?? null)   || (int) ($row['ratio_to'] ?? 0) < 1
            || ! strtotime($row['date'])) {
            return 'invalid';
        }

        $stock = Stock::where('symbol', strtoupper($row['symbol']))->first();
        if (! $stock) {
            return 'invalid';
        }

        if (StockSplit::where('stock_id', $stock->id)->whereDate('split_date', $row['date'])->exists()) {
            return 'duplicate';
        }

        return 'valid';
    }

    private function importSplitRow(array $row, int $rowNum, bool $skipDuplicates): true|array
    {
        if (empty($row['date']) || empty($row['symbol'])
            || ! is_numeric($row['ratio_from'] ?? null) || (int) ($row['ratio_from'] ?? 0) < 1
            || ! is_numeric($row['ratio_to'] ?? null)   || (int) ($row['ratio_to'] ?? 0) < 1
            || ! strtotime($row['date'])) {
            return ['row' => $rowNum, 'reason' => 'Invalid or missing split data'];
        }

        $stock = Stock::where('symbol', strtoupper($row['symbol']))->first();
        if (! $stock) {
            return ['row' => $rowNum, 'reason' => 'Stock not found — import transactions first'];
        }

        if ($skipDuplicates && StockSplit::where('stock_id', $stock->id)->whereDate('split_date', $row['date'])->exists()) {
            return ['row' => $rowNum, 'reason' => 'Duplicate'];
        }

        StockSplit::create([
            'stock_id'   => $stock->id,
            'split_date' => $row['date'],
            'ratio_from' => (int) $row['ratio_from'],
            'ratio_to'   => (int) $row['ratio_to'],
        ]);

        return true;
    }

    private function parseFile($file, string $format): array
    {
        $content = file_get_contents($file->getRealPath());

        if ($format === 'json') {
            return $this->parseJson($content, fn(array $item) => [
                'date'            => $item['date']            ?? '',
                'symbol'          => $item['symbol']          ?? '',
                'type'            => $item['type']            ?? '',
                'shares'          => $item['shares']          ?? '',
                'price_per_share' => $item['price_per_share'] ?? '',
                'handling_fee'    => $item['handling_fee']    ?? 0,
                'transaction_tax' => $item['transaction_tax'] ?? 0,
                'notes'           => $item['notes']           ?? '',
                'ratio_from'      => $item['ratio_from']      ?? null,
                'ratio_to'        => $item['ratio_to']        ?? null,
            ]);
        }

        return $this->parseCsv($content);
    }
}
