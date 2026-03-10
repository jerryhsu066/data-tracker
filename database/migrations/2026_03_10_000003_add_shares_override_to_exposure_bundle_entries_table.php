<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exposure_bundle_entries', function (Blueprint $table) {
            $table->decimal('shares_override', 16, 4)->nullable()->after('stock_id');
        });
    }

    public function down(): void
    {
        Schema::table('exposure_bundle_entries', function (Blueprint $table) {
            $table->dropColumn('shares_override');
        });
    }
};
