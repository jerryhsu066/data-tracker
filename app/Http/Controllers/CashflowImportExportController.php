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

        $types              = CashflowType::where('user_id', $userId)->whereNull('deleted_at')->get()->keyBy('name');
        $subtypes           = CashflowSubtype::where('user_id', $userId)->whereNull('deleted_at')->get()->groupBy('name');
        $typesWithSubtypes  = array_flip(
            CashflowSubtype::where('user_id', $userId)->whereNull('deleted_at')->pluck('type_id')->unique()->all()
        );

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
            $row         = array_map('trim', $row);
            $typeName    = $row['type']    ?? '';
            $subtypeName = $row['subtype'] ?? '';
            $year        = (int) ($row['year']  ?? 0);
            $month       = (int) ($row['month'] ?? 0);
            $amount      = $row['amount'] ?? '';

            if (!$types->has($typeName)) {
                $invalid[] = ['row' => $rowNum, 'reason' => "Unknown type: {$typeName}"];
                continue;
            }

            if (!is_numeric($amount) || $year < 2000 || $month < 1 || $month > 12) {
                $invalid[] = ['row' => $rowNum, 'reason' => 'Invalid amount or date'];
                continue;
            }

            $type             = $types[$typeName];
            $typeHasSubtypes  = isset($typesWithSubtypes[$type->id]);
            $subtypeId        = null;

            if ($subtypeName !== '') {
                $match = $subtypes->get($subtypeName)?->first(fn($s) => $s->type_id === $type->id);
                if (!$match) {
                    $invalid[] = ['row' => $rowNum, 'reason' => "Unknown subtype '{$subtypeName}' for type '{$typeName}'"];
                    continue;
                }
                $subtypeId = $match->id;
            } elseif ($typeHasSubtypes) {
                $invalid[] = ['row' => $rowNum, 'reason' => "Type '{$typeName}' requires a subtype"];
                continue;
            }

            if (CashflowRecord::where('user_id', $userId)
                    ->where('type_id', $type->id)
                    ->where('subtype_id', $subtypeId)
                    ->whereYear('recorded_at', $year)
                    ->whereMonth('recorded_at', $month)
                    ->where('amount', (float) $amount)
                    ->exists()) {
                $label = $typeName . ($subtypeName ? " / {$subtypeName}" : '') . " {$year}-{$month}";
                $duplicates[] = ['row' => $rowNum, 'label' => $label];
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

        $types             = CashflowType::where('user_id', $userId)->whereNull('deleted_at')->get()->keyBy('name');
        $subtypes          = CashflowSubtype::where('user_id', $userId)->whereNull('deleted_at')->get()->groupBy('name');
        $typesWithSubtypes = array_flip(
            CashflowSubtype::where('user_id', $userId)->whereNull('deleted_at')->pluck('type_id')->unique()->all()
        );

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

            $typeName    = $row['type']    ?? '';
            $subtypeName = $row['subtype'] ?? '';
            $year        = (int) ($row['year']  ?? 0);
            $month       = (int) ($row['month'] ?? 0);
            $amount      = $row['amount'] ?? '';

            if (!$types->has($typeName)) {
                $skipped[] = ['row' => $rowNum, 'reason' => "Unknown type: {$typeName}"];
                continue;
            }

            if (!is_numeric($amount) || $year < 2000 || $month < 1 || $month > 12) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Invalid amount or date'];
                continue;
            }

            $type            = $types[$typeName];
            $typeHasSubtypes = isset($typesWithSubtypes[$type->id]);
            $subtypeId       = null;

            if ($subtypeName !== '') {
                $match = $subtypes->get($subtypeName)?->first(fn($s) => $s->type_id === $type->id);
                if (!$match) {
                    $skipped[] = ['row' => $rowNum, 'reason' => "Unknown subtype '{$subtypeName}' for type '{$typeName}'"];
                    continue;
                }
                $subtypeId = $match->id;
            } elseif ($typeHasSubtypes) {
                $skipped[] = ['row' => $rowNum, 'reason' => "Type '{$typeName}' requires a subtype"];
                continue;
            }

            if ($skipDuplicates && CashflowRecord::where('user_id', $userId)
                    ->where('type_id', $type->id)
                    ->where('subtype_id', $subtypeId)
                    ->whereYear('recorded_at', $year)
                    ->whereMonth('recorded_at', $month)
                    ->where('amount', (float) $amount)
                    ->exists()) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Duplicate'];
                continue;
            }

            CashflowRecord::create([
                'user_id'     => $userId,
                'type_id'     => $type->id,
                'subtype_id'  => $subtypeId,
                'amount'      => $amount,
                'note'        => $row['note'] === '' ? null : $row['note'],
                'recorded_at' => sprintf('%04d-%02d-01', $year, $month),
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
                    '_row'    => $i + 1,
                    'year'    => $item['year']    ?? 0,
                    'month'   => $item['month']   ?? 0,
                    'type'    => $item['type']    ?? '',
                    'subtype' => $item['subtype'] ?? '',
                    'amount'  => $item['amount']  ?? '',
                    'note'    => $item['note']    ?? '',
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
