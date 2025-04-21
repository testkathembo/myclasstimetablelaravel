<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Classroom;

class ClassroomSeeder extends Seeder
{
    public function run()
    {
        $classrooms = [
            
            ['name' => 'Bleu Sky', 'capacity' => 40, 'location' => 'Phase1'],
            ['name' => 'AUDITORIUM', 'capacity' => 50, 'location' => 'Phase2'],
           
            ['name' => 'STMB 5th Floor', 'capacity' => 45, 'location' => 'Phase2'],
           
            
        ];

        foreach ($classrooms as $classroom) {
            Classroom::create($classroom);
        }
    }
}
