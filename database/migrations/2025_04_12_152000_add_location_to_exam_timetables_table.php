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
            $table->string('location')->nullable()->after('venue'); // Add the 'location' column after 'venue'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->dropColumn('location'); // Drop the 'location' column
        });
    }
};
