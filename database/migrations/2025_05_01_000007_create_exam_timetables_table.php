<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExamTimetablesTable extends Migration
{
    public function up(): void
    {
        Schema::create('exam_timetables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('day');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue');
            $table->string('location')->nullable();
            $table->integer('no');
            $table->string('chief_invigilator');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_timetables');
    }
}
