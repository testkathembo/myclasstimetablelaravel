<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Get the programs that belong to this school.
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * Get the units that belong to this school.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get the enrollments associated with this school.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the class timetables associated with this school.
     */
    public function classTimetables(): HasMany
    {
        return $this->hasMany(ClassTimetable::class);
    }

    /**
     * Get the exam timetables associated with this school.
     */
    public function examTimetables(): HasMany
    {
        return $this->hasMany(ExamTimetable::class);
    }
}