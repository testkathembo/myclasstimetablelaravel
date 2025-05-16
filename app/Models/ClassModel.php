<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'semester_id',
        'program_id',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
