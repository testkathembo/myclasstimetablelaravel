<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'credit_hours',
        'program_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the program that owns the unit.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school that owns the unit.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the enrollments for this unit.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the class timetables for this unit.
     */
    public function classTimetables(): HasMany
    {
        return $this->hasMany(ClassTimetable::class);
    }

    /**
     * Get the exam timetables for this unit.
     */
    public function examTimetables(): HasMany
    {
        return $this->hasMany(ExamTimetable::class);
    }

    /**
     * Scope a query to only include active units.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the semesters associated with this unit.
     */
    public function semesters()
    {
        return $this->belongsToMany(Semester::class, 'semester_unit')
            ->withPivot('class_id')
            ->withTimestamps();
    }
}