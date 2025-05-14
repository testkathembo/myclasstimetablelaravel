<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('semester_id')->default(1)->change(); // Set default value to 1
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('semester_id')->nullable()->change(); // Revert to nullable without default
        });
    }
};
