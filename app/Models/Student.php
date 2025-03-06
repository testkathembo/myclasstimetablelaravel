<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'semester_id'];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class);
    }

    public static function boot()
    {
        parent::boot();

        static::created(function ($student) {
            $student->units()->sync($student->semester->units->pluck('id')->toArray());
        });

        static::updated(function ($student) {
            if ($student->isDirty('semester_id')) {
                $student->units()->sync($student->semester->units->pluck('id')->toArray());
            }
        });
    }
}
