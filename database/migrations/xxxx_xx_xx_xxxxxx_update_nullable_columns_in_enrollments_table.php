<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateNullableColumnsInEnrollmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('student_code')->nullable()->change(); // Make student_code nullable
            $table->string('lecturer_code')->nullable()->change(); // Make lecturer_code nullable
            $table->string('group_id', 1)->nullable()->change(); // Make group_id nullable
            $table->unsignedBigInteger('program_id')->nullable()->change(); // Make program_id nullable
            $table->unsignedBigInteger('school_id')->nullable()->change(); // Make school_id nullable
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('student_code')->nullable(false)->change(); // Revert student_code to NOT NULL
            $table->string('lecturer_code')->nullable(false)->change(); // Revert lecturer_code to NOT NULL
            $table->string('group_id', 1)->nullable(false)->change(); // Revert group_id to NOT NULL
            $table->unsignedBigInteger('program_id')->nullable(false)->change(); // Revert program_id to NOT NULL
            $table->unsignedBigInteger('school_id')->nullable(false)->change(); // Revert school_id to NOT NULL
        });
    }
}
