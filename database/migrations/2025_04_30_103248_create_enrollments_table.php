<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_code')->constrained('users')->onDelete('cascade');
            $table->foreignId('lecturer_code')->constrained('users')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');            
            $table->foreignId('semester_id')
            ->nullable()
                ->default(1) // Set default value to 1 (assuming semester 1.1 has ID 1)
                ->constrained()
                ->onDelete('cascade')
                ->change();          
            $table->timestamps();
        });        
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
