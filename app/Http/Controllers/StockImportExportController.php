<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockImportExportController extends Controller
{
    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->get('format', 'csv');

        $transactions = Transaction::with('stock')
            ->where('user_id', $request->user()->id)
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
        ];

        if ($format === 'json') {
            $data = $transactions->isEmpty()
                ? [$example]
                : $transactions->map(fn($t) => [
                    'date'            => $t->transacted_at->format('Y-m-d'),
                    'symbol'          => $t->stock->symbol,
                    'type'            => $t->type,
                    'shares'          => $t->shares,
                    'price_per_share' => $t->price_per_share,
                    'handling_fee'    => $t->handling_fee,
                    'transaction_tax' => $t->transaction_tax,
                    'notes'           => $t->notes,
                ]);

            return response()->json($data)
                ->header('Content-Disposition', 'attachment; filename="transactions.json"');
        }

        return response()->stream(function () use ($transactions, $example) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'symbol', 'type', 'shares', 'price_per_share', 'handling_fee', 'transaction_tax', 'notes']);
            if ($transactions->isEmpty()) {
                fputcsv($handle, array_values($example));
            }
            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->transacted_at->format('Y-m-d'),
                    $t->stock->symbol,
                    $t->type,
                    $t->shares,
                    $t->price_per_share,
                    $t->handling_fee,
                    $t->transaction_tax,
                    $t->notes ?? '',
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="transactions.csv"',
        ]);
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file'],
            'format' => ['sometimes', 'in:csv,json'],
        ]);

        $format = $request->get('format', 'csv');
        $userId = $request->user()->id;
        $rows   = $this->parseFile($request->file('file'), $format);

        $invalid    = [];
        $duplicates = [];
        $valid      = 0;

        foreach ($rows as $row) {
            $rowNum = $row['_row'];

            if (isset($row['_parse_error'])) {
                $invalid[] = ['row' => $rowNum, 'reason' => $row['_parse_error']];
                continue;
            }

            unset($row['_row']);
            $row = array_map('trim', $row);

            if (empty($row['date']) || empty($row['symbol']) || empty($row['type'])
                || !is_numeric($row['shares'] ?? null)
                || !is_numeric($row['price_per_share'] ?? null)
                || !strtotime($row['date'])) {
                $invalid[] = ['row' => $rowNum, 'reason' => 'Invalid or missing data'];
                continue;
            }

            $stock = Stock::where('symbol', strtoupper($row['symbol']))->first();
            if ($stock && Transaction::where('user_id', $userId)
                    ->where('stock_id', $stock->id)
                    ->where('type', $row['type'])
                    ->whereDate('transacted_at', $row['date'])
                    ->where('shares', (int) $row['shares'])
                    ->where('price_per_share', (float) $row['price_per_share'])
                    ->exists()) {
                $duplicates[] = [
                    'row'   => $rowNum,
                    'label' => strtoupper($row['symbol']) . " {$row['type']} on {$row['date']}",
                ];
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

    public function import(Request $request): JsonResponse
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

        $imported = 0;
        $skipped  = [];

        foreach ($rows as $row) {
            $rowNum = $row['_row'];

            if (isset($row['_parse_error'])) {
                $skipped[] = ['row' => $rowNum, 'reason' => $row['_parse_error']];
                continue;
            }

            unset($row['_row']);
            $row = array_map('trim', $row);

            if (empty($row['date']) || empty($row['symbol']) || empty($row['type'])
                || !is_numeric($row['shares'] ?? null)
                || !is_numeric($row['price_per_share'] ?? null)
                || !strtotime($row['date'])) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Invalid or missing data'];
                continue;
            }

            $stock = Stock::firstOrCreate(
                ['symbol' => strtoupper($row['symbol'])],
                ['name'   => strtoupper($row['symbol'])]
            );

            if ($skipDuplicates && Transaction::where('user_id', $userId)
                    ->where('stock_id', $stock->id)
                    ->where('type', $row['type'])
                    ->whereDate('transacted_at', $row['date'])
                    ->where('shares', (int) $row['shares'])
                    ->where('price_per_share', (float) $row['price_per_share'])
                    ->exists()) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Duplicate'];
                continue;
            }

            Transaction::create([
                'user_id'         => $userId,
                'stock_id'        => $stock->id,
                'type'            => $row['type'],
                'shares'          => $row['shares'],
                'price_per_share' => $row['price_per_share'],
                'handling_fee'    => $row['handling_fee'] ?? 0,
                'transaction_tax' => $row['transaction_tax'] ?? 0,
                'transacted_at'   => $row['date'],
                'notes'           => $row['notes'] === '' ? null : $row['notes'],
            ]);

            $imported++;
        }

        return response()->json(['imported' => $imported, 'skipped' => $skipped]);
    }

    // ── parseFile ─────────────────────────────────────────────────────────────
    // Returns an array of rows. Each row always contains '_row' (1-based number).
    // Rows that cannot be parsed include '_parse_error' instead of field data.

    private function parseFile($file, string $format): array
    {
        $content = file_get_contents($file->getRealPath());

        if ($format === 'json') {
            $data = json_decode($content, true);
            if (!is_array($data)) {
                return [['_row' => 1, '_parse_error' => 'Invalid JSON — file could not be parsed']];
            }
            $rows = [];
            foreach ($data as $i => $item) {
                if (!is_array($item)) {
                    $rows[] = ['_row' => $i + 1, '_parse_error' => 'Item is not a JSON object'];
                    continue;
                }
                $rows[] = [
                    '_row'            => $i + 1,
                    'date'            => $item['date']            ?? '',
                    'symbol'          => $item['symbol']          ?? '',
                    'type'            => $item['type']            ?? '',
                    'shares'          => $item['shares']          ?? '',
                    'price_per_share' => $item['price_per_share'] ?? '',
                    'handling_fee'    => $item['handling_fee']    ?? 0,
                    'transaction_tax' => $item['transaction_tax'] ?? 0,
                    'notes'           => $item['notes']           ?? '',
                ];
            }
            return $rows;
        }

        // CSV
        $lines  = array_values(array_filter(explode("\n", trim($content)), fn($l) => trim($l) !== ''));
        $header = null;
        $rowNum = 0;
        $rows   = [];

        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            if ($header === null) {
                $header = $cols;
                $rowNum = 1;
                continue;
            }
            $rowNum++;
            if (count($cols) !== count($header)) {
                $rows[] = [
                    '_row'         => $rowNum,
                    '_parse_error' => 'Expected ' . count($header) . ' columns, found ' . count($cols),
                ];
                continue;
            }
            $row         = array_combine($header, $cols);
            $row['_row'] = $rowNum;
            $rows[]      = $row;
        }

        return $rows;
    }
}
