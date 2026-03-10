<?php

namespace Database\Seeders;

use App\Jobs\FetchHistoricalPrices;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class IndexStockSeeder extends Seeder
{
    private const INDICES = [
        '^TWII' => 'Taiwan Weighted Index',
        '^IXIC' => 'NASDAQ Composite',
        '^VIX'  => 'CBOE Volatility Index',
    ];

    public function run(): void
    {
        $fromDate = Carbon::today()->subMonth()->toDateString();

        foreach (self::INDICES as $symbol => $name) {
            $stock = Stock::firstOrCreate(
                ['symbol' => $symbol],
                ['name'   => $name],
            );

            if ($stock->wasRecentlyCreated) {
                FetchHistoricalPrices::dispatch($stock, $fromDate);
            }
        }
    }
}
