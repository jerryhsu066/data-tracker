<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'stock_id',
        'type',
        'shares',
        'price_per_share',
        'handling_fee',
        'transaction_tax',
        'transacted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shares' => 'decimal:4',
            'price_per_share' => 'decimal:4',
            'handling_fee' => 'decimal:4',
            'transaction_tax' => 'decimal:4',
            'transacted_at' => 'date:Y-m-d',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
