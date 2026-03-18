<?php

namespace App\Policies;

use App\Models\Flight;
use App\Models\User;

class FlightPolicy
{
    public function update(User $user, Flight $flight): bool
    {
        return $user->id === $flight->user_id;
    }

    public function delete(User $user, Flight $flight): bool
    {
        return $user->id === $flight->user_id;
    }
}
