<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamTimetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'semester_id',
        'unit_code',
        'day',
        'date',
        'start_time',
        'end_time',
        'venue',
        'location',
        'no',
        'chief_invigilator',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class); // Reference the enrollments table
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
