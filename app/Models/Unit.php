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
}
