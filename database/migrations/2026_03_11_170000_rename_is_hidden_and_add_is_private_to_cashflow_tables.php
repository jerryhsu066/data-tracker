<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashflow_types', function (Blueprint $table) {
            $table->renameColumn('is_hidden', 'is_disabled');
            $table->boolean('is_private')->default(true)->after('is_disabled');
        });

        Schema::table('cashflow_subtypes', function (Blueprint $table) {
            $table->renameColumn('is_hidden', 'is_disabled');
            $table->boolean('is_private')->default(true)->after('is_disabled');
        });
    }

    public function down(): void
    {
        Schema::table('cashflow_types', function (Blueprint $table) {
            $table->dropColumn('is_private');
            $table->renameColumn('is_disabled', 'is_hidden');
        });

        Schema::table('cashflow_subtypes', function (Blueprint $table) {
            $table->dropColumn('is_private');
            $table->renameColumn('is_disabled', 'is_hidden');
        });
    }
};
