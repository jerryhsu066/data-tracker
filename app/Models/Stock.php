<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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
        return $this->hasMany(StockTransaction::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(StockPriceHistory::class)->orderBy('date');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(StockSplit::class)->orderBy('split_date');
    }

    public function netSharesForUser(int $userId): float
    {
        $transactions = $this->transactions()->where('user_id', $userId)->get();
        $splits = $this->splits()->get();

        return self::calcNetShares($transactions, $splits);
    }

    public static function calcNetShares(Collection $transactions, Collection $splits): float
    {
        $net = 0.0;
        foreach ($transactions as $tx) {
            $multiplier = self::splitMultiplierSince((string) $tx->transacted_at, $splits);
            $adjusted = (float) $tx->shares * $multiplier;
            $net += $tx->type === 'buy' ? $adjusted : -$adjusted;
        }
        return $net;
    }

    /**
     * Returns the cumulative split multiplier that applies to a transaction dated $txDate.
     * This is the product of all split ratios for splits that occurred after $txDate.
     */
    public static function splitMultiplierSince(string $txDate, Collection $splits): float
    {
        $multiplier = 1.0;
        foreach ($splits as $split) {
            if ((string) $split->split_date > $txDate) {
                $multiplier *= $split->ratio_to / $split->ratio_from;
            }
        }
        return $multiplier;
    }

    /**
     * Returns the cumulative split multiplier for a transaction at $txDate
     * as of a specific portfolio history date $asOfDate.
     * Only splits that occurred after $txDate and on or before $asOfDate are included.
     */
    public static function splitMultiplierBetween(string $txDate, string $asOfDate, Collection $splits): float
    {
        $multiplier = 1.0;
        foreach ($splits as $split) {
            $splitDate = (string) $split->split_date;
            if ($splitDate > $txDate && $splitDate <= $asOfDate) {
                $multiplier *= $split->ratio_to / $split->ratio_from;
            }
        }
        return $multiplier;
    }
}
