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
        Schema::dropIfExists('holdings');

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('shares', 16, 4);
            $table->decimal('price_per_share', 12, 4);
            $table->date('transacted_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
