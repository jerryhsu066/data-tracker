<?php

namespace App\Policies;

use App\Models\ExposureBundle;
use App\Models\User;

class ExposureBundlePolicy
{
    public function update(User $user, ExposureBundle $bundle): bool
    {
        return $user->id === $bundle->user_id;
    }

    public function delete(User $user, ExposureBundle $bundle): bool
    {
        return $user->id === $bundle->user_id;
    }
}
