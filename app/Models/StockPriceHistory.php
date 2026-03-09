<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPriceHistory extends Model
{
    /** @use HasFactory<\Database\Factories\StockPriceHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'date',
        'close_price',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'close_price' => 'decimal:4',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
