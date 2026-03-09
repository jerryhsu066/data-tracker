<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('close_price', 12, 4);
            $table->timestamps();

            $table->unique(['stock_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_price_histories');
    }
};
