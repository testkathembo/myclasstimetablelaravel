<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateEnrollmentsForeignKeys extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Drop existing foreign key constraints
            $table->dropForeign(['student_code']);
            $table->dropForeign(['lecturer_code']);

            // Modify the columns to match the `code` field in the `users` table
            $table->string('student_code')->change();
            $table->string('lecturer_code')->change();

            // Add new foreign key constraints
            $table->foreign('student_code')->references('code')->on('users')->onDelete('cascade');
            $table->foreign('lecturer_code')->references('code')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Drop the new foreign key constraints
            $table->dropForeign(['student_code']);
            $table->dropForeign(['lecturer_code']);

            // Revert the columns to their original type
            $table->bigInteger('student_code')->unsigned()->change();
            $table->bigInteger('lecturer_code')->unsigned()->change();

            // Add back the original foreign key constraints
            $table->foreign('student_code')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lecturer_code')->references('id')->on('users')->onDelete('cascade');
        });
    }
}
