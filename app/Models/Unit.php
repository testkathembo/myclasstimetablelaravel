<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    // Make sure the Unit model has the correct table name and fillable fields
    protected $table = 'units';

    protected $fillable = [
        'code',
        'name',
        'semester_id',
        'faculty_id', // Assuming the `units` table has a `faculty_id` column
        // Add other fields as needed
    ];

    // Make sure the casts are defined correctly
    protected $casts = [
        'semester_id' => 'integer', // Ensure semester_id is cast to integer
    ];

    // Define relationships
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function examTimetables()
    {
        return $this->hasMany(ExamTimetable::class);
    }

    /**
     * Get the faculty that owns the unit.
     */
    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id'); // Assuming `faculty_id` is the foreign key
    }

    /**
     * Get the lecturer assigned to this unit.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code'); // Assuming `lecturer_code` references `users.code`
    }
}
