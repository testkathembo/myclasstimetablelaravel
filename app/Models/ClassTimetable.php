<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTimetable extends Model
{
    use HasFactory;

    protected $table = 'class_timetable';

    protected $fillable = [
        'semester_id',
        'unit_id',
        'day',
        'start_time',
        'end_time',
        'venue',
        'location',
        'no',
        'lecturer',
        'group',
        'program_id',
        'school_id',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the unit that owns the class timetable.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester that owns the class timetable.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the program that owns the class timetable.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school that owns the class timetable.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the lecturer that owns the class timetable.
     * This assumes you have a User or Lecturer model.
     */
    public function lecturerRelation()
    {
        return $this->belongsTo(User::class, 'lecturer', 'code');
    }
}