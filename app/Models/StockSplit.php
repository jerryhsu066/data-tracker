<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSplit extends Model
{
    protected $fillable = ['stock_id', 'split_date', 'ratio_from', 'ratio_to'];

    protected function casts(): array
    {
        return [
            'split_date' => 'date:Y-m-d',
            'ratio_from' => 'integer',
            'ratio_to'   => 'integer',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function multiplier(): float
    {
        return $this->ratio_to / $this->ratio_from;
    }
}
