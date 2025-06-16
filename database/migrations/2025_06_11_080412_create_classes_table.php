<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "1.1", "2.2"
            $table->foreignId('semester_id')->constrained()->onDelete('cascade'); // Links to semesters
            $table->foreignId('program_id')->constrained()->onDelete('cascade'); // Links to programs
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
