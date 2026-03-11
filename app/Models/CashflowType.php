<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashflowType extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'name', 'is_expense', 'sort_order', 'is_disabled', 'is_private', 'merge_subtypes'];

    protected $casts = ['is_expense' => 'boolean', 'is_disabled' => 'boolean', 'is_private' => 'boolean', 'merge_subtypes' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subtypes(): HasMany
    {
        return $this->hasMany(CashflowSubtype::class, 'type_id')
                    ->orderBy('sort_order')
                    ->orderBy('id');
    }
}
