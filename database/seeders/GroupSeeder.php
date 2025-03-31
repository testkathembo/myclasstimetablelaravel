<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group;

class GroupSeeder extends Seeder
{
    public function run()
    {
        $groups = [
            ['name' => 'Group A'],
            ['name' => 'Group B'],
            ['name' => 'Group C'],
            ['name' => 'Group D'],
        ];

        foreach ($groups as $group) {
            Group::create($group);
        }
    }
}
