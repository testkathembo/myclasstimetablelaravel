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
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->default(1)->change(); // Set a default value for unit_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->default(null)->change(); // Remove the default value
        });
    }
};
