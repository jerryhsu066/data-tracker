<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exposure_bundle_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('exposure_bundles')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->decimal('leverage', 8, 2)->default(1.00);
            $table->boolean('is_cash')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exposure_bundle_entries');
    }
};
