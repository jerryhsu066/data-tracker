<?php

namespace App\Policies;

use App\Models\CashflowRecord;
use App\Models\User;

class CashflowRecordPolicy
{
    public function update(User $user, CashflowRecord $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function delete(User $user, CashflowRecord $record): bool
    {
        return $user->id === $record->user_id;
    }
}
