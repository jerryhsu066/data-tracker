<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holding extends Model
{
    /** @use HasFactory<\Database\Factories\HoldingFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'shares',
        'average_cost',
    ];

    protected function casts(): array
    {
        return [
            'shares' => 'decimal:4',
            'average_cost' => 'decimal:4',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function getCurrentValueAttribute(): string
    {
        $price = $this->stock?->current_price ?? 0;

        return number_format((float) $this->shares * (float) $price, 4, '.', '');
    }

    public function getGainLossAttribute(): string
    {
        $price = $this->stock?->current_price ?? 0;

        return number_format(((float) $price - (float) $this->average_cost) * (float) $this->shares, 4, '.', '');
    }
}
