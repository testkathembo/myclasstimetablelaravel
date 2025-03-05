<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;
use Faker\Factory as Faker;

class UnitBbitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();

        for ($i = 2; $i < 55; $i++) {
            Unit::create([
                'code' => $faker->unique()->numerify('BBIT###'), 
                '' => $faker->word,           
            ]);
        }
    }
}
