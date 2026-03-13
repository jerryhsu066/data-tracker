<?php

namespace App\Http\Controllers\Concerns;

trait ParsesImportFile
{
    /**
     * Parse a CSV file into an array of associative rows.
     * Each row includes '_row' (1-based). Malformed rows include '_parse_error'.
     */
    private function parseCsv(string $content): array
    {
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

    /**
     * Decode a JSON file into an array of items.
     * Returns a single-element array with '_parse_error' if the JSON is invalid.
     * Each valid item is passed through $mapItem to extract module-specific fields.
     */
    private function parseJson(string $content, callable $mapItem): array
    {
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
            $rows[] = ['_row' => $i + 1, ...$mapItem($item)];
        }
        return $rows;
    }
}
