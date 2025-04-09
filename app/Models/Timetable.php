<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'classroom_id',
        'lecturer_id',
        'semester_id',
        'day',
        'date',
        'start_time',
        'end_time',
        'group',
        'venue',
        'no',
        'chief_invigilator',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
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
