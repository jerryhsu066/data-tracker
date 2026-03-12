<?php

namespace App\Policies;

use App\Models\StockTransaction;
use App\Models\User;

class StockTransactionPolicy
{
    public function update(User $user, StockTransaction $stockTransaction): bool
    {
        return $user->id === $stockTransaction->user_id;
    }

    public function delete(User $user, StockTransaction $stockTransaction): bool
    {
        return $user->id === $stockTransaction->user_id;
    }
}
