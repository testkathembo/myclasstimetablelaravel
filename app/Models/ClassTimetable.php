<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTimetable extends Model
{
    use HasFactory;

    protected $table = 'class_timetable';

    // ✅ FIXED: Updated fillable array with correct field names
    protected $fillable = [
        'semester_id',
        'unit_id',
        'class_id',      // ✅ FIXED: was missing
        'group_id',      // ✅ FIXED: was 'group' instead of 'group_id'
        'day',
        'start_time',
        'end_time',
        'teaching_mode', // ✅ ADDED: for teaching mode functionality
        'venue',
        'location',
        'no',
        'lecturer',
        'program_id',
        'school_id',
        'teaching_mode', // ✅ ADDED: for teaching mode functionality
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the unit that owns the class timetable.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester that owns the class timetable.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * ✅ ADDED: Get the class that owns the class timetable.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /**
     * ✅ ADDED: Get the group that owns the class timetable.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * Get the program that owns the class timetable.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school that owns the class timetable.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the lecturer that owns the class timetable.
     * This assumes you have a User or Lecturer model.
     */
    public function lecturerRelation()
    {
        return $this->belongsTo(User::class, 'lecturer', 'code');
    }
}