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
        'type_id',
        'subtype_id',
        'amount',
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

    public function type(): BelongsTo
    {
        return $this->belongsTo(CashflowType::class, 'type_id');
    }

    public function subtype(): BelongsTo
    {
        return $this->belongsTo(CashflowSubtype::class, 'subtype_id');
    }
}
