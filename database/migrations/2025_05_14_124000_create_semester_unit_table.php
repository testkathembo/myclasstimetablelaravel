<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('semester_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->onDelete('cascade'); // Link to semesters
            $table->foreignId('unit_id')->constrained()->onDelete('cascade'); // Link to units
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('semester_unit');
    }
};
