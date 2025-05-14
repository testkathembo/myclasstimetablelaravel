<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamTimetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'unit_id',
        'day',
        'date',
        'start_time',
        'end_time',
        'venue',
        'location',
        'no',
        'chief_invigilator',
        'group',
        'program_id',
        'school_id',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the unit that owns the exam timetable.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester that owns the exam timetable.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the program that owns the exam timetable.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school that owns the exam timetable.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the chief invigilator that owns the exam timetable.
     * This assumes you have a User or Staff model.
     */
    public function chiefInvigilator()
    {
        return $this->belongsTo(User::class, 'chief_invigilator', 'code');
    }
}