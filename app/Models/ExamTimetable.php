<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamTimetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'day',
        'date',
        'unit_id',
        'semester_id',
        'class_id',
        'venue',
        'location',
        'no',
        'chief_invigilator',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    /**
     * Get the unit that owns the exam timetable.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester that owns the exam timetable.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the class that owns the exam timetable.
     */
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
}