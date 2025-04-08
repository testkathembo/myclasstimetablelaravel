<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    use HasFactory;

    protected $table = 'time_slots'; // Explicitly define the table name

    protected $fillable = [
        'day',
        'date',
        'start_time',
        'end_time',
    ];
}
