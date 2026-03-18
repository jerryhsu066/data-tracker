<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $fillable = [
        'iata',
        'name',
        'city',
        'country',
        'lat',
        'lng',
        'tz',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];
}
