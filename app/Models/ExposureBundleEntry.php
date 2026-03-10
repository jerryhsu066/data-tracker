<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExposureBundleEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bundle_id',
        'stock_id',
        'leverage',
        'is_cash',
    ];

    protected function casts(): array
    {
        return [
            'leverage' => 'decimal:2',
            'is_cash'  => 'boolean',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ExposureBundle::class, 'bundle_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
