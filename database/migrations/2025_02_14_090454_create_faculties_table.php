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
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Unique faculty code e.g., 'SCI', 'BUS'
            $table->string('name')->unique(); // Faculty name
            $table->text('description')->nullable(); // Optional description
            $table->string('dean')->nullable(); // Name of faculty dean
            $table->string('contact_email')->unique(); // Faculty contact email
            $table->string('contact_phone')->nullable(); // Contact phone number
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculties');
    }
};
