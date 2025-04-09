Schema::create('timetables', function (Blueprint $table) {
    $table->id();
    $table->foreignId('unit_id')->constrained()->onDelete('cascade');
    $table->foreignId('classroom_id')->constrained()->onDelete('cascade');
    $table->foreignId('lecturer_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('semester_id')->constrained()->onDelete('cascade');
    $table->string('day');
    $table->date('date');
    $table->time('start_time');
    $table->time('end_time');
    $table->string('group')->nullable();
    $table->string('venue')->nullable();
    $table->integer('no')->nullable();
    $table->string('chief_invigilator')->nullable();
    $table->timestamps();
});
