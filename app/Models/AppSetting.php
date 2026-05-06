<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['registration_enabled', 'public_flight_user_id'];

    protected function casts(): array
    {
        return [
            'registration_enabled'  => 'boolean',
            'public_flight_user_id' => 'integer',
        ];
    }

    public static function get(): self
    {
        return self::firstOrCreate(['id' => 1], ['registration_enabled' => true]);
    }
}
