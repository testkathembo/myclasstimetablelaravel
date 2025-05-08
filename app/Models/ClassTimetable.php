<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassTimetable extends Model
{
    use HasFactory;

    // Specify the table name
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
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function getEnrolledStudents()
    {
        // Make sure relationships are loaded
        $this->load(['unit', 'semester']);
        
        // Find all student codes enrolled in this unit for this semester
        $studentCodes = Enrollment::where('unit_id', $this->unit_id)
            ->where('semester_id', $this->semester_id)
            ->pluck('student_code')
            ->toArray();
            
        // Get all users with these codes
        return User::whereIn('code', $studentCodes)->get();
    }
}
