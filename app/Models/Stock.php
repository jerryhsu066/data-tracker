<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    /** @use HasFactory<\Database\Factories\StockFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'symbol',
        'name',
        'current_price',
        'previous_close',
        'change_percent',
        'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:4',
            'previous_close' => 'decimal:4',
            'change_percent' => 'decimal:4',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(StockPriceHistory::class)->orderBy('date');
    }

    public function netSharesForUser(int $userId): float
    {
        $bought = (float) $this->transactions()->where('type', 'buy')->where('user_id', $userId)->sum('shares');
        $sold = (float) $this->transactions()->where('type', 'sell')->where('user_id', $userId)->sum('shares');

        return $bought - $sold;
    }
}
