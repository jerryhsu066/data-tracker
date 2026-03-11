<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashflowSubtype extends Model
{
    use SoftDeletes;

    protected $fillable = ['type_id', 'user_id', 'name', 'sort_order', 'is_hidden'];

    protected $casts = ['is_hidden' => 'boolean'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(CashflowType::class, 'type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
