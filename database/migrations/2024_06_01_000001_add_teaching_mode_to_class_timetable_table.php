<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeachingModeToClassTimetableTable extends Migration
{
    public function up()
    {
        Schema::table('class_timetable', function (Blueprint $table) {
            $table->string('teaching_mode', 50)->nullable()->after('end_time');
        });
    }

    public function down()
    {
        Schema::table('class_timetable', function (Blueprint $table) {
            $table->dropColumn('teaching_mode');
        });
    }
}
