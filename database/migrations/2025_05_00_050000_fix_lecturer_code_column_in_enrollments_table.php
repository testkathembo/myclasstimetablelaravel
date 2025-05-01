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
            $table->dropForeign(['lecturer_code']);

            // Change the `lecturer_code` column to an unsignedBigInteger
            $table->unsignedBigInteger('lecturer_code')->nullable()->change();

            // Add the foreign key constraint back
            $table->foreign('lecturer_code')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['lecturer_code']);

            // Change the `lecturer_code` column back to a string
            $table->string('lecturer_code')->nullable()->change();
        });
    }
};
