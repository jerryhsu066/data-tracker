<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashflowRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'recorded_at',
        'type',
        'amount',
        'company_id',
        'bank_id',
        'note',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'amount'      => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CashflowCompany::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(CashflowBank::class);
    }
}
