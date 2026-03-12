<?php

namespace App\Http\Controllers;

use App\Models\CashflowRecord;
use App\Models\CashflowSubtype;
use App\Models\CashflowType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashflowImportExportController extends Controller
{
    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->get('format', 'csv');

        $records = CashflowRecord::with(['type', 'subtype'])
            ->where('user_id', $request->user()->id)
            ->orderBy('recorded_at')
            ->get();

        $example = [
            'year'    => 2024,
            'month'   => 1,
            'type'    => 'Credit Card',
            'subtype' => 'HSBC',
            'amount'  => 5000,
            'note'    => 'example row — delete before importing',
        ];

        if ($format === 'json') {
            $data = $records->isEmpty()
                ? [$example]
                : $records->map(fn($r) => [
                    'year'    => (int) $r->recorded_at->format('Y'),
                    'month'   => (int) $r->recorded_at->format('n'),
                    'type'    => $r->type->name,
                    'subtype' => $r->subtype?->name ?? '',
                    'amount'  => $r->amount,
                    'note'    => $r->note ?? '',
                ]);

            return response()->json($data)
                ->header('Content-Disposition', 'attachment; filename="cashflow.json"');
        }

        return response()->stream(function () use ($records, $example) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['year', 'month', 'type', 'subtype', 'amount', 'note']);
            if ($records->isEmpty()) {
                fputcsv($handle, array_values($example));
            }
            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->recorded_at->format('Y'),
                    $r->recorded_at->format('n'),
                    $r->type->name,
                    $r->subtype?->name ?? '',
                    $r->amount,
                    $r->note ?? '',
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cashflow.csv"',
        ]);
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file'],
            'format' => ['sometimes', 'in:csv,json'],
        ]);

        $format = $request->get('format', 'csv');
        $userId = $request->user()->id;
        $rows   = $this->parseFile($request->file('file'), $format);

        // Build lookup caches
        $types    = CashflowType::where('user_id', $userId)->whereNull('deleted_at')->get()->keyBy('name');
        $subtypes = CashflowSubtype::where('user_id', $userId)->whereNull('deleted_at')->get()->groupBy('name');

        $imported = 0;
        $skipped  = [];

        foreach ($rows as $i => $row) {
            $row = array_map('trim', $row);

            $typeName    = $row['type']    ?? '';
            $subtypeName = $row['subtype'] ?? '';
            $year        = (int) ($row['year']  ?? 0);
            $month       = (int) ($row['month'] ?? 0);
            $amount      = $row['amount'] ?? '';

            if (!$types->has($typeName)) {
                $skipped[] = ['row' => $i + 2, 'reason' => "Unknown type: {$typeName}"];
                continue;
            }

            if (!is_numeric($amount) || $year < 2000 || $month < 1 || $month > 12) {
                $skipped[] = ['row' => $i + 2, 'reason' => 'Invalid amount or date'];
                continue;
            }

            $type      = $types[$typeName];
            $subtypeId = null;

            if ($subtypeName !== '') {
                $match = $subtypes->get($subtypeName)?->first(fn($s) => $s->type_id === $type->id);
                $subtypeId = $match?->id;
            }

            CashflowRecord::create([
                'user_id'     => $userId,
                'type_id'     => $type->id,
                'subtype_id'  => $subtypeId,
                'amount'      => $amount,
                'note'        => $row['note'] ?? null,
                'recorded_at' => sprintf('%04d-%02d-01', $year, $month),
            ]);

            $imported++;
        }

        return response()->json(['imported' => $imported, 'skipped' => $skipped]);
    }

    private function parseFile($file, string $format): array
    {
        $content = file_get_contents($file->getRealPath());

        if ($format === 'json') {
            $data = json_decode($content, true) ?? [];
            return array_map(fn($item) => [
                'year'    => $item['year']    ?? 0,
                'month'   => $item['month']   ?? 0,
                'type'    => $item['type']    ?? '',
                'subtype' => $item['subtype'] ?? '',
                'amount'  => $item['amount']  ?? '',
                'note'    => $item['note']    ?? null,
            ], $data);
        }

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
