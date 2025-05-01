<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Drop the existing foreign key constraint if it exists
            $table->dropForeign(['student_code']);

            // Ensure `student_code` is an unsignedBigInteger
            $table->unsignedBigInteger('student_code')->nullable()->change();

            // Add the foreign key constraint back
            $table->foreign('student_code')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['student_code']);

            // Revert `student_code` to its previous type
            $table->string('student_code')->nullable()->change();
        });
    }
};
