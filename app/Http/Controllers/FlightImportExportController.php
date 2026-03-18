<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FlightImportExportController extends Controller
{
    use Concerns\ParsesImportFile;

    private const CSV_COLUMNS = ['flight_date', 'airline', 'flight_number', 'departure_airport', 'arrival_airport', 'departure_time', 'arrival_time', 'aircraft_type', 'seat_class', 'seat_number', 'booking_reference', 'ticket_price', 'tail_number', 'notes'];

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->get('format', 'csv');

        $flights = Flight::where('user_id', $request->user()->id)
            ->orderBy('flight_date')
            ->get();

        $example = [
            'flight_date'       => '2026-03-15',
            'airline'           => 'China Airlines',
            'flight_number'     => 'CI123',
            'departure_airport' => 'TPE',
            'arrival_airport'   => 'NRT',
            'departure_time'    => '2026-03-15 08:30:00',
            'arrival_time'      => '2026-03-15 12:45:00',
            'aircraft_type'     => 'A330-300',
            'seat_class'        => 'economy',
            'seat_number'       => '32A',
            'booking_reference' => 'ABC123',
            'ticket_price'      => 15000,
            'tail_number'       => 'B-18302',
            'notes'             => 'example row — delete before importing',
        ];

        if ($format === 'json') {
            $data = $flights->isEmpty()
                ? [$example]
                : $flights->map(fn($f) => [
                    'flight_date'       => $f->flight_date->format('Y-m-d'),
                    'airline'           => $f->airline,
                    'flight_number'     => $f->flight_number,
                    'departure_airport' => $f->departure_airport,
                    'arrival_airport'   => $f->arrival_airport,
                    'departure_time'    => $f->departure_time?->format('Y-m-d H:i:s') ?? '',
                    'arrival_time'      => $f->arrival_time?->format('Y-m-d H:i:s') ?? '',
                    'aircraft_type'     => $f->aircraft_type ?? '',
                    'seat_class'        => $f->seat_class ?? '',
                    'seat_number'       => $f->seat_number ?? '',
                    'booking_reference' => $f->booking_reference ?? '',
                    'ticket_price'      => $f->ticket_price ?? '',
                    'tail_number'       => $f->tail_number ?? '',
                    'notes'             => $f->notes ?? '',
                ]);

            return response()->json($data)
                ->header('Content-Disposition', 'attachment; filename="flights.json"');
        }

        return response()->stream(function () use ($flights, $example) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::CSV_COLUMNS);
            if ($flights->isEmpty()) {
                fputcsv($handle, array_values($example));
            }
            foreach ($flights as $f) {
                fputcsv($handle, [
                    $f->flight_date->format('Y-m-d'),
                    $f->airline,
                    $f->flight_number,
                    $f->departure_airport,
                    $f->arrival_airport,
                    $f->departure_time?->format('Y-m-d H:i:s') ?? '',
                    $f->arrival_time?->format('Y-m-d H:i:s') ?? '',
                    $f->aircraft_type ?? '',
                    $f->seat_class ?? '',
                    $f->seat_number ?? '',
                    $f->booking_reference ?? '',
                    $f->ticket_price ?? '',
                    $f->tail_number ?? '',
                    $f->notes ?? '',
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="flights.csv"',
        ]);
    }

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

            $flightDate       = $row['flight_date'] ?? '';
            $airline          = $row['airline'] ?? '';
            $flightNumber     = $row['flight_number'] ?? '';
            $departureAirport = $row['departure_airport'] ?? '';
            $arrivalAirport   = $row['arrival_airport'] ?? '';

            if ($flightDate === '' || $airline === '' || $flightNumber === '' || $departureAirport === '' || $arrivalAirport === '') {
                $invalid[] = ['row' => $rowNum, 'reason' => 'Missing required fields (flight_date, airline, flight_number, departure_airport, arrival_airport)'];
                continue;
            }

            if (!strtotime($flightDate)) {
                $invalid[] = ['row' => $rowNum, 'reason' => 'Invalid date format'];
                continue;
            }

            if (strlen($departureAirport) !== 3 || strlen($arrivalAirport) !== 3) {
                $invalid[] = ['row' => $rowNum, 'reason' => 'Airport codes must be exactly 3 characters'];
                continue;
            }

            if (Flight::where('user_id', $userId)
                    ->whereDate('flight_date', $flightDate)
                    ->where('flight_number', $flightNumber)
                    ->where('departure_airport', $departureAirport)
                    ->where('arrival_airport', $arrivalAirport)
                    ->exists()) {
                $label = "{$flightNumber} {$departureAirport}→{$arrivalAirport} {$flightDate}";
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

            $flightDate       = $row['flight_date'] ?? '';
            $airline          = $row['airline'] ?? '';
            $flightNumber     = $row['flight_number'] ?? '';
            $departureAirport = $row['departure_airport'] ?? '';
            $arrivalAirport   = $row['arrival_airport'] ?? '';

            if ($flightDate === '' || $airline === '' || $flightNumber === '' || $departureAirport === '' || $arrivalAirport === '') {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Missing required fields'];
                continue;
            }

            if (!strtotime($flightDate)) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Invalid date format'];
                continue;
            }

            if (strlen($departureAirport) !== 3 || strlen($arrivalAirport) !== 3) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Airport codes must be exactly 3 characters'];
                continue;
            }

            if ($skipDuplicates && Flight::where('user_id', $userId)
                    ->whereDate('flight_date', $flightDate)
                    ->where('flight_number', $flightNumber)
                    ->where('departure_airport', $departureAirport)
                    ->where('arrival_airport', $arrivalAirport)
                    ->exists()) {
                $skipped[] = ['row' => $rowNum, 'reason' => 'Duplicate'];
                continue;
            }

            Flight::create([
                'user_id'           => $userId,
                'flight_date'       => $flightDate,
                'airline'           => $airline,
                'flight_number'     => $flightNumber,
                'departure_airport' => $departureAirport,
                'arrival_airport'   => $arrivalAirport,
                'departure_time'    => ($row['departure_time'] ?? '') !== '' ? $row['departure_time'] : null,
                'arrival_time'      => ($row['arrival_time'] ?? '') !== '' ? $row['arrival_time'] : null,
                'aircraft_type'     => ($row['aircraft_type'] ?? '') !== '' ? $row['aircraft_type'] : null,
                'seat_class'        => ($row['seat_class'] ?? '') !== '' ? $row['seat_class'] : null,
                'seat_number'       => ($row['seat_number'] ?? '') !== '' ? $row['seat_number'] : null,
                'booking_reference' => ($row['booking_reference'] ?? '') !== '' ? $row['booking_reference'] : null,
                'ticket_price'      => ($row['ticket_price'] ?? '') !== '' ? $row['ticket_price'] : null,
                'tail_number'       => ($row['tail_number'] ?? '') !== '' ? $row['tail_number'] : null,
                'notes'             => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
            ]);

            $imported++;
        }

        return response()->json(['imported' => $imported, 'skipped' => $skipped]);
    }

    private function parseFile($file, string $format): array
    {
        $content = file_get_contents($file->getRealPath());

        if ($format === 'json') {
            return $this->parseJson($content, fn(array $item) => collect(self::CSV_COLUMNS)
                ->mapWithKeys(fn($col) => [$col => $item[$col] ?? ''])
                ->all());
        }

        return $this->parseCsv($content);
    }
}
