<?php

namespace Tests\Unit;

use App\Services\Fr24ImportService;
use PHPUnit\Framework\TestCase;

class Fr24ImportServiceTest extends TestCase
{
    private Fr24ImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new Fr24ImportService();
    }

    // ── HTML page parsing (actual jerryhsu066 format) ─────────────────────────

    public function test_parse_html_page_extracts_flight_data(): void
    {
        // Actual HTML structure from my.flightradar24.com/jerryhsu066/flights
        // IATA codes are plain text inside <span class="tooltip">
        // Seat class is in the flight-icons column (col 12), NOT the seat column
        $html = <<<'HTML'
        <html><body>
        <table>
            <tr data-row-number="0">
                <td class="flight-date"><span class="inner-date">2026-02-21</span></td>
                <td class="flight-flight">JL99</td>
                <td class="flight-reg">JA873J</td>
                <td class="flight-from"><span class="tooltip" data-tooltip-value="Tokyo / Haneda">HND</span></td>
                <td class="flight-to"><span class="tooltip" data-tooltip-value="Taipei / Taipei Songshan Airport">TSA</span></td>
                <td class="flight-distance">2,099</td>
                <td class="flight-dep-time">18:10</td>
                <td class="flight-arr-time">21:00</td>
                <td class="flight-airline"><span class="tooltip" data-tooltip-value="Japan Airlines">JAL</span></td>
                <td class="flight-aircraft"><span class="tooltip" data-tooltip-value="Boeing 787-9">B789</span></td>
                <td class="flight-seat"><span class="circle-icon tooltip" data-tooltip-value="Window">W</span>1K</td>
                <td class="flight-note"><span></span></td>
                <td class="flight-icons"><span class="circle-icon class-business tooltip" data-tooltip-value="Business">B</span></td>
            </tr>
        </table>
        </body></html>
        HTML;

        $flights = $this->service->parseHtmlPage($html);

        $this->assertCount(1, $flights);
        $f = $flights[0];
        $this->assertEquals('2026-02-21', $f['flight_date']);
        $this->assertEquals('JL99', $f['flight_number']);
        $this->assertEquals('HND', $f['departure_airport']);
        $this->assertEquals('TSA', $f['arrival_airport']);
        $this->assertEquals('2026-02-21 18:10:00', $f['departure_time']);
        $this->assertEquals('2026-02-21 21:00:00', $f['arrival_time']);
        $this->assertEquals('JAL', $f['airline']);
        $this->assertEquals('B789', $f['aircraft_type']);
        $this->assertEquals('JA873J', $f['tail_number']);
        $this->assertEquals('business', $f['seat_class']);
        $this->assertEquals('1K', $f['seat_number']);
    }

    public function test_parse_html_row_with_empty_reg_and_no_seat(): void
    {
        $html = <<<'HTML'
        <html><body><table>
            <tr data-row-number="0">
                <td class="flight-date"><span class="inner-date">2027-01-04</span></td>
                <td class="flight-flight">JL52</td>
                <td class="flight-reg"></td>
                <td class="flight-from"><span class="tooltip" data-tooltip-value="Sydney / Kingsford Smith">SYD</span></td>
                <td class="flight-to"><span class="tooltip" data-tooltip-value="Tokyo / Haneda">HND</span></td>
                <td class="flight-distance">7,826</td>
                <td class="flight-dep-time">09:15</td>
                <td class="flight-arr-time">16:55</td>
                <td class="flight-airline"><span class="tooltip" data-tooltip-value="Japan Airlines">JAL</span></td>
                <td class="flight-aircraft"><span class="tooltip" data-tooltip-value="Boeing 777-300">B773</span></td>
                <td class="flight-seat"><span class="circle-icon tooltip" data-tooltip-value="Aisle">A</span>1D</td>
                <td class="flight-note"><span></span></td>
                <td class="flight-icons"><span class="circle-icon class-business tooltip" data-tooltip-value="Business">B</span></td>
            </tr>
        </table></body></html>
        HTML;

        $flights = $this->service->parseHtmlPage($html);

        $this->assertCount(1, $flights);
        $f = $flights[0];
        $this->assertEquals('2027-01-04', $f['flight_date']);
        $this->assertEquals('JL52', $f['flight_number']);
        $this->assertEquals('SYD', $f['departure_airport']);
        $this->assertEquals('HND', $f['arrival_airport']);
        $this->assertNull($f['tail_number']);
        $this->assertEquals('business', $f['seat_class']);
        $this->assertEquals('1D', $f['seat_number']);
    }

    public function test_parse_html_skips_row_with_fewer_than_13_cells(): void
    {
        $html = <<<'HTML'
        <html><body><table>
            <tr data-row-number="0">
                <td>2026-01-01</td><td>CI123</td><td>TPE</td>
            </tr>
        </table></body></html>
        HTML;

        $this->assertCount(0, $this->service->parseHtmlPage($html));
    }

    // ── JSON pagination parsing (actual jerryhsu066 format) ──────────────────

    public function test_parse_json_row_extracts_flight_data(): void
    {
        // Actual JSON format from /public-scripts/flight-list/jerryhsu066/50/0/0
        // Date has inner-actions with Edit/Delete links — must NOT be included
        $columns = [
            "<span class='inner-date'>2018-10-29</span><span class='inner-actions'><a href='/edit-flight/abc'>Edit</a> | <a href='/delete-flight/abc'>Delete</a></span>",
            'TG636',
            '<a href="https://my.flightradar24.com/airport/bangkok-suvarnabhumi-vtbs" class="show-hovercard" data-hovercard-content="Bangkok / Suvarnabhumi">BKK</a>',
            '<a href="https://my.flightradar24.com/airport/taipei-taoyuan-rctp" class="show-hovercard" data-hovercard-content="Taipei / Taoyuan">TPE</a>',
            '1,548',
            '17:40',
            '22:10',
            '<a href="https://my.flightradar24.com/airline/thai-airways-tha" class="show-hovercard" data-hovercard-content="Thai Airways">THA</a>',
            '<a href="https://my.flightradar24.com/aircraft/boeing-777-200-b772" class="show-hovercard" data-hovercard-content="Boeing 777-200">B772</a>',
            'HS-TJB',
            ' <span class="circle-icon tooltip" data-tooltip-value="Middle">M</span>51E',
            '',
            "<span class='circle-icon class-economy tooltip' data-tooltip-value='Economy'>E</span><span class='circle-icon reason-leisure tooltip' data-tooltip-value='Leisure'>L</span>",
        ];

        $flight = $this->service->parseJsonRow($columns);

        $this->assertNotNull($flight);
        $this->assertEquals('2018-10-29', $flight['flight_date']);
        $this->assertEquals('TG636', $flight['flight_number']);
        $this->assertEquals('BKK', $flight['departure_airport']);
        $this->assertEquals('TPE', $flight['arrival_airport']);
        $this->assertEquals('2018-10-29 17:40:00', $flight['departure_time']);
        $this->assertEquals('2018-10-29 22:10:00', $flight['arrival_time']);
        $this->assertEquals('THA', $flight['airline']);
        $this->assertEquals('B772', $flight['aircraft_type']);
        $this->assertEquals('HS-TJB', $flight['tail_number']);
        $this->assertEquals('economy', $flight['seat_class']);
        $this->assertEquals('51E', $flight['seat_number']);
    }

    public function test_parse_json_row_with_empty_reg_and_no_seat(): void
    {
        // Row 51 from jerryhsu066: empty registration, empty seat number
        $columns = [
            "<span class='inner-date'>2019-07-26</span><span class='inner-actions'><a href='/edit-flight/abc'>Edit</a></span>",
            'B78721',
            '<a href="https://my.flightradar24.com/airport/taipei-sungshan-rcss" class="show-hovercard" data-hovercard-content="Taipei / Taipei Songshan Airport">TSA</a>',
            '<a href="https://my.flightradar24.com/airport/taitung-fengnin-rcfn" class="show-hovercard" data-hovercard-content="Taitung / Fengnin">TTT</a>',
            '163',
            '09:40',
            '10:45',
            '<a href="https://my.flightradar24.com/airline/bra-transportes-aereos-brb" class="show-hovercard" data-hovercard-content="BRA Transportes Aereos">BRB</a>',
            '<a href="https://my.flightradar24.com/aircraft/atr-72-200-at72" class="show-hovercard" data-hovercard-content="ATR 72-200">AT72</a>',
            '',
            ' <span class="circle-icon tooltip" data-tooltip-value=""></span>',
            '',
            "<span class='circle-icon class-economy tooltip' data-tooltip-value='Economy'>E</span>",
        ];

        $flight = $this->service->parseJsonRow($columns);

        $this->assertNotNull($flight);
        $this->assertEquals('2019-07-26', $flight['flight_date']);
        $this->assertEquals('TSA', $flight['departure_airport']);
        $this->assertEquals('TTT', $flight['arrival_airport']);
        $this->assertEquals('AT72', $flight['aircraft_type']);
        $this->assertNull($flight['tail_number']);
        $this->assertNull($flight['seat_number']);
        $this->assertEquals('economy', $flight['seat_class']);
    }

    public function test_parse_json_row_returns_null_for_short_array(): void
    {
        $this->assertNull($this->service->parseJsonRow(['2026-01-01', 'CI123']));
    }

    // ── Date extraction ───────────────────────────────────────────────────────

    public function test_json_date_ignores_edit_delete_links(): void
    {
        $columns = array_fill(0, 13, '');
        $columns[0] = "<span class='inner-date'>2020-05-15</span><span class='inner-actions'><a href='/edit-flight/xyz'>Edit</a> | <a href='/delete-flight/xyz' onclick='return confirm(...)'>Delete</a></span>";
        $columns[2] = '<a href="...">TPE</a>';
        $columns[3] = '<a href="...">NRT</a>';

        $flight = $this->service->parseJsonRow($columns);
        $this->assertEquals('2020-05-15', $flight['flight_date']);
    }

    // ── Seat class mapping ────────────────────────────────────────────────────

    public function test_map_seat_class(): void
    {
        $this->assertEquals('first', Fr24ImportService::mapSeatClass('class-first'));
        $this->assertEquals('business', Fr24ImportService::mapSeatClass('class-business'));
        $this->assertEquals('economy+', Fr24ImportService::mapSeatClass('class-economy-plus'));
        $this->assertEquals('economy', Fr24ImportService::mapSeatClass('class-economy'));
        $this->assertNull(Fr24ImportService::mapSeatClass('unknown'));
    }

    // ── Next-day arrival detection ────────────────────────────────────────────

    public function test_next_day_arrival_when_arr_time_before_dep_time(): void
    {
        // SYD→HND: dep 09:15 local, arr 16:55 next day (cross-dateline example)
        $columns = array_fill(0, 13, '');
        $columns[0] = "<span class='inner-date'>2027-01-04</span>";
        $columns[1] = 'JL52';
        $columns[2] = '<a href="...">SYD</a>';
        $columns[3] = '<a href="...">HND</a>';
        $columns[5] = '23:55';
        $columns[6] = '06:00';

        $flight = $this->service->parseJsonRow($columns);
        $this->assertEquals('2027-01-04 23:55:00', $flight['departure_time']);
        $this->assertEquals('2027-01-05 06:00:00', $flight['arrival_time']);
    }

    public function test_same_day_arrival_when_arr_after_dep(): void
    {
        $columns = array_fill(0, 13, '');
        $columns[0] = "<span class='inner-date'>2026-02-21</span>";
        $columns[1] = 'JL99';
        $columns[2] = '<a href="...">HND</a>';
        $columns[3] = '<a href="...">TSA</a>';
        $columns[5] = '18:10';
        $columns[6] = '21:00';

        $flight = $this->service->parseJsonRow($columns);
        $this->assertEquals('2026-02-21 18:10:00', $flight['departure_time']);
        $this->assertEquals('2026-02-21 21:00:00', $flight['arrival_time']);
    }
}
