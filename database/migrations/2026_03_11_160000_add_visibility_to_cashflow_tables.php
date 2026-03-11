<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashflow_types', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('sort_order');
            $table->boolean('merge_subtypes')->default(false)->after('is_hidden');
        });

        Schema::table('cashflow_subtypes', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('cashflow_types', function (Blueprint $table) {
            $table->dropColumn(['is_hidden', 'merge_subtypes']);
        });

        Schema::table('cashflow_subtypes', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
