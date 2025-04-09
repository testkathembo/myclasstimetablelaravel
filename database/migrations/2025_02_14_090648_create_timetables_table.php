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
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained();
            $table->foreignId('enrollment_id')->constrained(); // Replace unit_id with enrollment_id
            $table->foreignId('lecturer_id')->constrained('users');
            $table->foreignId('time_slot_id')->constrained(); // Link to time_slots table
            $table->foreignId('semester_id')->constrained(); // Link to semesters table
            $table->timestamps();

            // Add unique constraint to prevent collisions
            $table->unique(['time_slot_id', 'enrollment_id', 'semester_id'], 'unique_timetable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
