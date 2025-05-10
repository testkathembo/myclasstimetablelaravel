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
        'day',
        'unit_id',
        'semester_id',
        'venue',
        'location',
        'no',
        'lecturer',
        'start_time',
        'end_time',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
