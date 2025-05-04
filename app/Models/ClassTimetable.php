<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassTimetable extends Model
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
        'status',
        'lecturer_id',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}
