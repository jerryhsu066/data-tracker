<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('handling_fee', 12, 4)->default(0)->after('price_per_share');
            $table->decimal('transaction_tax', 12, 4)->default(0)->after('handling_fee');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['handling_fee', 'transaction_tax']);
        });
    }
};
