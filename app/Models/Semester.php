<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
    ];

    public function units()
    {
        return $this->belongsToMany(Unit::class, 'semester_unit')
            ->withPivot('class_id')
            ->withTimestamps();
    }
}