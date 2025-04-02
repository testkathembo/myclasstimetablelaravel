<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'unit_id', 'semester_id']; // Ensure semester_id is fillable

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class); // Define relationship with Semester
    }
}
