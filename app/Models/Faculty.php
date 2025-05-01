<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',        
    ];

    /**
     * Get the units that belong to the faculty.
     */
    public function units()
    {
        return $this->hasMany(Unit::class, 'faculty_id'); // Assuming `faculty_id` is the foreign key in the `units` table
    }
}
