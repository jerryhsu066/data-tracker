<?php

namespace App\Jobs;

use App\Models\Stock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchAllStockPrices implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Stock::each(fn (Stock $stock) => FetchStockPrice::dispatch($stock));
    }
}
