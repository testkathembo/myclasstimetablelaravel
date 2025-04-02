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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('semester_id')
                ->nullable()
                ->default(1) // Set default value to 1 (assuming semester 1.1 has ID 1)
                ->constrained()
                ->onDelete('cascade')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('semester_id')
                ->nullable(false)
                ->default(null)
                ->change();
        });
    }
};
