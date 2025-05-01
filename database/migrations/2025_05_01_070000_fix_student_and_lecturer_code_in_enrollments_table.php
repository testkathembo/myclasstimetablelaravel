<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Clean up invalid data before adding foreign key constraints
            DB::statement('UPDATE enrollments SET student_code = NULL WHERE student_code NOT IN (SELECT code FROM users)');
            DB::statement('UPDATE enrollments SET lecturer_code = NULL WHERE lecturer_code NOT IN (SELECT code FROM users)');

            // Change `student_code` and `lecturer_code` to string to match `users.code`
            $table->string('student_code')->nullable()->change();
            $table->string('lecturer_code')->nullable()->change();

            // Add foreign key constraints referencing `users.code`
            $table->foreign('student_code')->references('code')->on('users')->onDelete('set null');
            $table->foreign('lecturer_code')->references('code')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['student_code']);
            $table->dropForeign(['lecturer_code']);

            // Revert `student_code` and `lecturer_code` to unsignedBigInteger
            $table->unsignedBigInteger('student_code')->nullable()->change();
            $table->unsignedBigInteger('lecturer_code')->nullable()->change();
        });
    }
};
