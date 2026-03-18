<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flight extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'flight_date',
        'airline',
        'flight_number',
        'departure_airport',
        'arrival_airport',
        'departure_time',
        'arrival_time',
        'aircraft_type',
        'seat_class',
        'seat_number',
        'booking_reference',
        'ticket_price',
        'tail_number',
        'notes',
    ];

    protected $casts = [
        'flight_date'    => 'date:Y-m-d',
        'departure_time' => 'string',
        'arrival_time'   => 'string',
        'ticket_price'   => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
