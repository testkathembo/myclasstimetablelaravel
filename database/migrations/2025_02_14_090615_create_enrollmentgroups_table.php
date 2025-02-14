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
        Schema::create('enrollment_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Computer Science - Year 1"
            $table->foreignId('semester_id')->constrained(); // Link to Semester
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollmentgroups');
    }
};
