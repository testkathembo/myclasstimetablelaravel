<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'group_id'];

    public $timestamps = true;

    public function groups()
    {
        return $this->hasMany(Group::class); // Define relationship with Group
    }
}
