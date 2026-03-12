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

    // ── Import ────────────────────────────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file'],
            'format' => ['sometimes', 'in:csv,json'],
        ]);

        $format  = $request->get('format', 'csv');
        $userId  = $request->user()->id;
        $rows    = $this->parseFile($request->file('file'), $format);

        $imported = 0;
        $skipped  = [];

        foreach ($rows as $i => $row) {
            $row = array_map('trim', $row);

            // Validate required fields
            if (empty($row['date']) || empty($row['symbol']) || empty($row['type'])
                || !is_numeric($row['shares'] ?? null)
                || !is_numeric($row['price_per_share'] ?? null)
                || !strtotime($row['date'])) {
                $skipped[] = ['row' => $i + 2, 'reason' => 'Invalid or missing data'];
                continue;
            }

            // Find or create stock
            $stock = Stock::firstOrCreate(
                ['symbol' => strtoupper($row['symbol'])],
                ['name'   => strtoupper($row['symbol'])]
            );

            Transaction::create([
                'user_id'         => $userId,
                'stock_id'        => $stock->id,
                'type'            => $row['type'],
                'shares'          => $row['shares'],
                'price_per_share' => $row['price_per_share'],
                'handling_fee'    => $row['handling_fee'] ?? 0,
                'transaction_tax' => $row['transaction_tax'] ?? 0,
                'transacted_at'   => $row['date'],
                'notes'           => $row['notes'] ?? null,
            ]);

            $imported++;
        }

        return response()->json(['imported' => $imported, 'skipped' => $skipped]);
    }

    private function parseFile($file, string $format): array
    {
        $path    = $file->getRealPath();
        $content = file_get_contents($path);

        if ($format === 'json') {
            $data = json_decode($content, true) ?? [];
            return array_map(fn($item) => [
                'date'            => $item['date']            ?? '',
                'symbol'          => $item['symbol']          ?? '',
                'type'            => $item['type']            ?? '',
                'shares'          => $item['shares']          ?? '',
                'price_per_share' => $item['price_per_share'] ?? '',
                'handling_fee'    => $item['handling_fee']    ?? 0,
                'transaction_tax' => $item['transaction_tax'] ?? 0,
                'notes'           => $item['notes']           ?? null,
            ], $data);
        }

        // CSV
        $lines  = array_filter(explode("\n", trim($content)));
        $header = null;
        $rows   = [];

        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            if ($header === null) {
                $header = $cols;
                continue;
            }
            if (count($cols) === count($header)) {
                $rows[] = array_combine($header, $cols);
            }
        }

        return $rows;
    }
}
