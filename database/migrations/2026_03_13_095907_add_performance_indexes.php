<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('stock_id');
        });

        Schema::table('stock_price_histories', function (Blueprint $table) {
            $table->index(['stock_id', 'date']);
        });

        Schema::table('cashflow_records', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('cashflow_type_id');
        });

        Schema::table('exposure_bundles', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('exposure_bundle_entries', function (Blueprint $table) {
            $table->index('bundle_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['stock_id']);
        });

        Schema::table('stock_price_histories', function (Blueprint $table) {
            $table->dropIndex(['stock_id', 'date']);
        });

        Schema::table('cashflow_records', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['cashflow_type_id']);
        });

        Schema::table('exposure_bundles', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('exposure_bundle_entries', function (Blueprint $table) {
            $table->dropIndex(['bundle_id']);
        });
    }
};
