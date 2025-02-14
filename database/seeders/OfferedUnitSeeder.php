<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OfferedUnit;

class OfferedUnitSeeder extends Seeder
{
    public function run()
    {
        OfferedUnit::factory(50)->create(); // Create 50 offered units
    }
}
