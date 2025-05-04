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
        Schema::table('class_timetables', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']); // Drop the foreign key constraint
            $table->dropColumn('enrollment_id'); // Drop the column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_timetables', function (Blueprint $table) {
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
        });
    }
};
