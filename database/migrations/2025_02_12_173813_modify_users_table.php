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
        Schema::table('users', function (Blueprint $table) {
            $table->string('code')->unique()->after('id');
            $table->string('first_name')->after('code');
            $table->string('last_name')->after('first_name');
            $table->string('faculty')->after('last_name');
            $table->string('phone')->unique()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['code', 'first_name', 'last_name', 'faculty', 'phone']);
        });
    }
};

