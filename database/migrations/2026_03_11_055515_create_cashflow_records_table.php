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
        Schema::create('cashflow_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->datetime('recorded_at');
            $table->enum('type', ['income', 'rent', 'credit_card', 'other']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('company_id')->nullable()->constrained('cashflow_companies')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('cashflow_banks')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashflow_records');
    }
};
