<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassTimetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'semester_id',
        'unit_id',
        'day',
        'date',
        'start_time',
        'end_time',
        'venue',
        'location',
        'no',
        'status',
        'lecturer_id',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
