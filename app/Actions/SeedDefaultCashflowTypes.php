<?php

namespace App\Actions;

use App\Models\CashflowSubtype;
use App\Models\CashflowType;
use App\Models\User;

class SeedDefaultCashflowTypes
{
    private const DEFAULTS = [
        ['name' => 'Income',       'is_expense' => false, 'merge_subtypes' => false, 'subtypes' => []],
        ['name' => 'Credit Card',  'is_expense' => true,  'merge_subtypes' => false, 'subtypes' => ['HSBC', 'CTBC']],
        ['name' => 'Housing',      'is_expense' => true,  'merge_subtypes' => true,  'subtypes' => ['Rent', 'Electricity', 'Water']],
        ['name' => 'Subscription', 'is_expense' => true,  'merge_subtypes' => true,  'subtypes' => ['Netflix', 'Spotify']],
    ];

    public function run(User $user): void
    {
        foreach (self::DEFAULTS as $i => $def) {
            $type = CashflowType::create([
                'user_id'        => $user->id,
                'name'           => $def['name'],
                'is_expense'     => $def['is_expense'],
                'merge_subtypes' => $def['merge_subtypes'],
                'sort_order'     => $i,
            ]);

            foreach ($def['subtypes'] as $j => $subName) {
                CashflowSubtype::create([
                    'cashflow_type_id' => $type->id,
                    'user_id'          => $user->id,
                    'name'             => $subName,
                    'sort_order'       => $j,
                ]);
            }
        }
    }
}
