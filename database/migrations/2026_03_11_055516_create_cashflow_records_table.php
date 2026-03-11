<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->datetime('recorded_at');
            $table->foreignId('type_id')->constrained('cashflow_types')->cascadeOnDelete();
            $table->foreignId('subtype_id')->nullable()->constrained('cashflow_subtypes')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_records');
    }
};
