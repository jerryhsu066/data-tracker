<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    /** @use HasFactory<\Database\Factories\StockFactory> */
    use HasFactory;

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

    public function holdings(): HasMany
    {
        return $this->hasMany(Holding::class);
    }
}
