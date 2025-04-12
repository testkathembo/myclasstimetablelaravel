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
            $table->unsignedBigInteger('enrollment_id')->nullable()->after('id'); // Add the 'enrollment_id' column
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade'); // Add foreign key constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']); // Drop the foreign key constraint
            $table->dropColumn('enrollment_id'); // Drop the 'enrollment_id' column
        });
    }
};
