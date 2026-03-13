<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exposure_bundles', function (Blueprint $table) {
            $table->decimal('cash', 14, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('exposure_bundles', function (Blueprint $table) {
            $table->bigInteger('cash')->default(0)->change();
        });
    }
};
