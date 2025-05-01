<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->string('location')->nullable()->after('venue'); // Add `location` column
        });
    }

    public function down(): void
    {
        Schema::table('exam_timetables', function (Blueprint $table) {
            $table->dropColumn('location'); // Remove `location` column
        });
    }
};
