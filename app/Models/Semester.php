<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public $timestamps = true;
    /**
     * Define the relationship with the Unit model.
     */
    public function units()
    {
        return $this->hasMany(Unit::class);
    }
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function classTimetables()
    {
        return $this->hasMany(classTimetable::class);
    }
    public function examTimetables()
    {
        return $this->hasMany(ExamTimetable::class);
    }

    
}
