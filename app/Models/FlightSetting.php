<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightSetting extends Model
{
    protected $fillable = [
        'user_id',
        'aviationstack_key',
        'aerodatabox_key',
        'fr24_username',
    ];

    protected $casts = [
        'aviationstack_key' => 'encrypted',
        'aerodatabox_key'   => 'encrypted',
    ];

    protected $hidden = [
        'aviationstack_key',
        'aerodatabox_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
