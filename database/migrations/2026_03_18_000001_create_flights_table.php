<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('flight_date');
            $table->string('airline', 100);
            $table->string('flight_number', 20);
            $table->string('departure_airport', 3);
            $table->string('arrival_airport', 3);
            $table->dateTime('departure_time')->nullable();
            $table->dateTime('arrival_time')->nullable();
            $table->string('aircraft_type', 50)->nullable();
            $table->string('seat_class', 20)->nullable();
            $table->string('seat_number', 10)->nullable();
            $table->string('booking_reference', 20)->nullable();
            $table->decimal('ticket_price', 10, 2)->nullable();
            $table->string('tail_number', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'flight_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
