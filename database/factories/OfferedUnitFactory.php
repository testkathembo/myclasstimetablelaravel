<?php
namespace Database\Factories;

use App\Models\OfferedUnit;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferedUnitFactory extends Factory
{
    protected $model = OfferedUnit::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::inRandomOrder()->first()->id ?? Unit::factory()->create()->id,
            'semester_id' => Semester::inRandomOrder()->first()->id ?? Semester::factory()->create()->id,
            'lecturer_id' => User::whereHas('role', function ($query) {
                $query->where('name', 'Lecturer');
            })->inRandomOrder()->first()->id ?? null, // Lecturer is optional
        ];
    }
}
