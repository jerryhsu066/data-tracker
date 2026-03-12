<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('transactions', 'stock_transactions');

        Schema::table('cashflow_subtypes', function (Blueprint $table) {
            $table->renameColumn('type_id', 'cashflow_type_id');
        });

        Schema::table('cashflow_records', function (Blueprint $table) {
            $table->renameColumn('type_id', 'cashflow_type_id');
            $table->renameColumn('subtype_id', 'cashflow_subtype_id');
        });
    }

    public function down(): void
    {
        Schema::rename('stock_transactions', 'transactions');

        Schema::table('cashflow_subtypes', function (Blueprint $table) {
            $table->renameColumn('cashflow_type_id', 'type_id');
        });

        Schema::table('cashflow_records', function (Blueprint $table) {
            $table->renameColumn('cashflow_type_id', 'type_id');
            $table->renameColumn('cashflow_subtype_id', 'subtype_id');
        });
    }
};
