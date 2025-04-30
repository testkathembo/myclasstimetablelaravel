<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixForeignKeysInEnrollments extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['student_code']);
            $table->dropForeign(['lecturer_code']);
            $table->foreign('student_code')->references('code')->on('users')->onDelete('cascade');
            $table->foreign('lecturer_code')->references('code')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['student_code']);
            $table->dropForeign(['lecturer_code']);
        });
    }
}
