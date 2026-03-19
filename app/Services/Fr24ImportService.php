<?php

namespace App\Services;

use App\Models\Flight;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class Fr24ImportService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const MAX_FLIGHTS = 10000;
    private const PAGE_DELAY_US = 500000; // 0.5s

    public function import(string $username, int $userId): array
    {
        $rawFlights = $this->fetchAllFlights($username);

        $imported = 0;
        $skipped = 0;

        foreach ($rawFlights as $flight) {
            if (empty($flight['flight_date']) || empty($flight['flight_number'])
                || empty($flight['departure_airport']) || empty($flight['arrival_airport'])) {
                $skipped++;
                continue;
            }

            $isDuplicate = Flight::where('user_id', $userId)
                ->whereDate('flight_date', $flight['flight_date'])
                ->where('flight_number', $flight['flight_number'])
                ->where('departure_airport', $flight['departure_airport'])
                ->where('arrival_airport', $flight['arrival_airport'])
                ->exists();

            if ($isDuplicate) {
                $skipped++;
                continue;
            }

            Flight::create([
                'user_id'           => $userId,
                'flight_date'       => $flight['flight_date'],
                'flight_number'     => $flight['flight_number'],
                'departure_airport' => $flight['departure_airport'],
                'arrival_airport'   => $flight['arrival_airport'],
                'departure_time'    => $flight['departure_time'],
                'arrival_time'      => $flight['arrival_time'],
                'airline'           => $flight['airline'] ?? 'Unknown',
                'aircraft_type'     => $flight['aircraft_type'],
                'tail_number'       => $flight['tail_number'],
                'seat_class'        => $flight['seat_class'],
                'seat_number'       => $flight['seat_number'],
                'notes'             => 'Imported from FR24',
                'import_source'     => 'fr24',
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'total'    => count($rawFlights),
        ];
    }

    public function fetchAllFlights(string $username): array
    {
        $flights = [];

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->get("https://my.flightradar24.com/{$username}/flights");

        if (!$response->ok()) {
            return [];
        }

        $flights = $this->parseHtmlPage($response->body());

        $lastRow = count($flights);
        while ($lastRow < self::MAX_FLIGHTS) {
            usleep(self::PAGE_DELAY_US);

            $jsonResponse = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->get("https://my.flightradar24.com/public-scripts/flight-list/{$username}/{$lastRow}/0/0");

            if (!$jsonResponse->ok()) {
                break;
            }

            $data = $jsonResponse->json();
            if (empty($data) || !is_array($data)) {
                break;
            }

            $pageFlights = [];
            foreach ($data as $columns) {
                if (!is_array($columns)) {
                    continue;
                }
                $parsed = $this->parseJsonRow($columns);
                if ($parsed) {
                    $pageFlights[] = $parsed;
                }
            }

            if (empty($pageFlights)) {
                break;
            }

            $flights = array_merge($flights, $pageFlights);
            $lastRow = count($flights);
        }

        return $flights;
    }

    public function parseHtmlPage(string $html): array
    {
        $flights = [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//tr[@data-row-number]');

        foreach ($rows as $row) {
            $parsed = $this->parseHtmlRow($row, $xpath);
            if ($parsed) {
                $flights[] = $parsed;
            }
        }

        return $flights;
    }

    /**
     * HTML table column layout (0-indexed):
     *  0  flight-date       <span class="inner-date">2027-01-04</span>
     *  1  flight-flight     JL52
     *  2  flight-reg        JA873J  (plain text, may be empty)
     *  3  flight-from       <span class="tooltip" …>SYD</span>
     *  4  flight-to         <span class="tooltip" …>HND</span>
     *  5  flight-distance   7,826
     *  6  flight-dep-time   09:15
     *  7  flight-arr-time   16:55
     *  8  flight-airline    <span class="tooltip" …>JAL</span>
     *  9  flight-aircraft   <span class="tooltip" …>B773</span>
     * 10  flight-seat       <span class="circle-icon …">A</span>1D
     * 11  flight-note       <span></span>
     * 12  flight-icons      <span class="circle-icon class-business …">B</span>
     */
    public function parseHtmlRow(\DOMElement $row, DOMXPath $xpath): ?array
    {
        $cells = $xpath->query('td', $row);
        if ($cells->length < 13) {
            return null;
        }

        $date         = trim($cells->item(0)->textContent);
        $flightNumber = trim($cells->item(1)->textContent);
        $registration = trim($cells->item(2)->textContent);
        $fromIata     = trim($cells->item(3)->textContent);
        $toIata       = trim($cells->item(4)->textContent);
        $depTime      = trim($cells->item(6)->textContent);
        $arrTime      = trim($cells->item(7)->textContent);
        $airline      = trim($cells->item(8)->textContent);
        $aircraft     = trim($cells->item(9)->textContent);
        $seatNumber   = $this->extractSeatNumberFromCell($cells->item(10));
        $seatClass    = $this->extractSeatClassFromHtml(
            $cells->item(12)->ownerDocument->saveHTML($cells->item(12))
        );

        return $this->buildFlightData(
            $date, $flightNumber, $fromIata, $toIata,
            $depTime, $arrTime, $airline, $aircraft,
            $registration ?: null, $seatClass, $seatNumber
        );
    }

    /**
     * JSON pagination column layout (0-indexed):
     *  0  date_html       <span class='inner-date'>2019-07-26</span><span class='inner-actions'>…</span>
     *  1  flight_number   B78721  (plain text)
     *  2  from_html       <a href="…">TSA</a>
     *  3  to_html         <a href="…">TTT</a>
     *  4  distance        163
     *  5  dep_time        09:40
     *  6  arr_time        10:45
     *  7  airline_html    <a href="…">BRB</a>
     *  8  aircraft_html   <a href="…">AT72</a>
     *  9  registration    HS-TJB  (plain text, may be empty)
     * 10  seat_html       <span class="circle-icon …">M</span>51E
     * 11  note            (plain text)
     * 12  icons_html      <span class='circle-icon class-economy …'>E</span>
     */
    public function parseJsonRow(array $columns): ?array
    {
        if (count($columns) < 13) {
            return null;
        }

        $date         = $this->extractDateFromHtml($columns[0]);
        $flightNumber = trim(strip_tags($columns[1]));
        $fromIata     = trim(strip_tags($columns[2]));
        $toIata       = trim(strip_tags($columns[3]));
        $depTime      = trim(strip_tags($columns[5]));
        $arrTime      = trim(strip_tags($columns[6]));
        $airline      = trim(strip_tags($columns[7]));
        $aircraft     = trim(strip_tags($columns[8]));
        $registration = trim(strip_tags($columns[9])) ?: null;
        $seatNumber   = $this->extractSeatNumberFromHtml($columns[10]);
        $seatClass    = $this->extractSeatClassFromHtml($columns[12]);

        return $this->buildFlightData(
            $date, $flightNumber, $fromIata, $toIata,
            $depTime, $arrTime, $airline, $aircraft,
            $registration, $seatClass, $seatNumber
        );
    }

    private function buildFlightData(
        string $date, string $flightNumber, string $fromIata, string $toIata,
        string $depTime, string $arrTime, string $airline, string $aircraft,
        ?string $registration, ?string $seatClass, ?string $seatNumber
    ): ?array {
        $parsedDate = $this->parseDate($date);
        if (!$parsedDate) {
            return null;
        }

        $departureTime = null;
        $arrivalTime   = null;

        if (preg_match('/^\d{2}:\d{2}$/', $depTime)) {
            $departureTime = "{$parsedDate} {$depTime}:00";
        }
        if (preg_match('/^\d{2}:\d{2}$/', $arrTime)) {
            $arrDate = $parsedDate;
            if ($departureTime && $arrTime < $depTime) {
                $arrDate = date('Y-m-d', strtotime($parsedDate . ' +1 day'));
            }
            $arrivalTime = "{$arrDate} {$arrTime}:00";
        }

        return [
            'flight_date'       => $parsedDate,
            'flight_number'     => $flightNumber ?: null,
            'departure_airport' => strtoupper($fromIata) ?: null,
            'arrival_airport'   => strtoupper($toIata) ?: null,
            'departure_time'    => $departureTime,
            'arrival_time'      => $arrivalTime,
            'airline'           => $airline ?: null,
            'aircraft_type'     => $aircraft ?: null,
            'tail_number'       => $registration,
            'seat_class'        => $seatClass,
            'seat_number'       => $seatNumber,
        ];
    }

    private function parseDate(string $date): ?string
    {
        $date = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        $ts = strtotime($date);
        if ($ts) {
            return date('Y-m-d', $ts);
        }
        return null;
    }

    /**
     * Extract date from JSON date_html — must use inner-date span,
     * not strip_tags (which would include "Edit | Delete" text).
     */
    private function extractDateFromHtml(string $html): string
    {
        if (preg_match('/inner-date[^>]*>(\d{4}-\d{2}-\d{2})/', $html, $m)) {
            return $m[1];
        }
        // Fallback for plain date strings
        return trim(strip_tags($html));
    }

    /**
     * Extract seat number from HTML seat cell.
     * The cell looks like: <span class="circle-icon …">A</span>\n1D
     * We want just "1D" — the number+letter, ignoring the position indicator letter.
     */
    private function extractSeatNumberFromHtml(string $html): ?string
    {
        $text = trim(strip_tags($html));
        if (preg_match('/(\d+[A-Z]?)/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract seat number from a DOM seat cell by reading only direct text nodes,
     * which skips the position-indicator span content (A/W/M).
     */
    private function extractSeatNumberFromCell(\DOMElement $cell): ?string
    {
        $text = '';
        foreach ($cell->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text .= $node->nodeValue;
            }
        }
        $text = trim($text);
        if (preg_match('/(\d+[A-Z]?)/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract seat class from the icons column HTML.
     * Looks for CSS classes: class-first, class-business, class-economy-plus, class-economy.
     * Must test economy-plus before economy to avoid partial match.
     */
    private function extractSeatClassFromHtml(string $html): ?string
    {
        if (str_contains($html, 'class-first')) {
            return 'first';
        }
        if (str_contains($html, 'class-business')) {
            return 'business';
        }
        if (str_contains($html, 'class-economy-plus')) {
            return 'economy+';
        }
        if (str_contains($html, 'class-economy')) {
            return 'economy';
        }
        return null;
    }

    public static function mapSeatClass(string $cssClass): ?string
    {
        return match ($cssClass) {
            'class-first'        => 'first',
            'class-business'     => 'business',
            'class-economy-plus' => 'economy+',
            'class-economy'      => 'economy',
            default              => null,
        };
    }
}
