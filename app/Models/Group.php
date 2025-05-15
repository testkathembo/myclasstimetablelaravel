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

    public function class()
    {
        return $this->belongsTo(ClassModel::class);
    }
}
