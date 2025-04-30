<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
