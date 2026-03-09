<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'type',
        'shares',
        'price_per_share',
        'transacted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shares' => 'decimal:4',
            'price_per_share' => 'decimal:4',
            'transacted_at' => 'date:Y-m-d',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
