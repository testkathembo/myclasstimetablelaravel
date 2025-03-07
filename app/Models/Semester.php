<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model {
    use HasFactory;

    protected $fillable = ['name'];

    public function units() {
        return $this->belongsToMany(Unit::class, 'semester_units');
    }
}
