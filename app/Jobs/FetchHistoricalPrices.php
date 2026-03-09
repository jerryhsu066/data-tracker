<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Services\StockPriceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchHistoricalPrices implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Stock $stock,
        public readonly string $fromDate,
    ) {}

    public function handle(StockPriceService $service): void
    {
        $service->fetchHistoricalPrices($this->stock, $this->fromDate);
    }
}
