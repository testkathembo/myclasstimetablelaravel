<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'school_id',
    ];

    /**
     * Get the school that owns the program.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the program groups for this program.
     */
    public function programGroups(): HasMany
    {
        return $this->hasMany(ProgramGroup::class);
    }

    /**
     * Get the units associated with this program.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get the enrollments associated with this program.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the class timetables associated with this program.
     */
    public function classTimetables(): HasMany
    {
        return $this->hasMany(ClassTimetable::class);
    }

    /**
     * Get the exam timetables associated with this program.
     */
    public function examTimetables(): HasMany
    {
        return $this->hasMany(ExamTimetable::class);
    }
}