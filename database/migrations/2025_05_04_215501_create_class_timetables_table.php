<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClassTimetablesTable extends Migration
{
    public function up()
    {
        Schema::create('class_timetables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('day');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue');
            $table->string('location')->nullable();      
            $table->string('status')->default('physical'); // Add status column
            $table->unsignedBigInteger('lecturer_id')->nullable();
            $table->timestamps();

            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->foreign('lecturer_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('class_timetables');
    }
}
