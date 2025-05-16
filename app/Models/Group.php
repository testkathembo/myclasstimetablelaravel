<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'class_id',
        'capacity',
    ];

    /**
     * Get the class associated with the group.
     */
    public function class()
    {
        return $this->belongsTo(ClassModel::class);
    }

    /**
     * Get the enrollments associated with the group.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'group_id');
    }
}
