<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Services\StockPriceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchStockPrice implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Stock $stock) {}

    public function handle(StockPriceService $service): void
    {
        $service->updatePrice($this->stock);
    }
}
