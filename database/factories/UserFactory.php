<?php
namespace Database\Factories;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomNumber(6), // Unique Student/Lecturer Code
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'faculty' => fake()->randomElement(['Science', 'Arts', 'Business']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => Role::inRandomOrder()->first()->id ?? 1, // Assign random role (Admin, Lecturer, Student)
        ];
    }

    /**
     * Indicate that the model should have an Admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('name', 'Admin')->first()->id,
        ]);
    }

    /**
     * Indicate that the model should have a Lecturer role.
     */
    public function lecturer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('name', 'Lecturer')->first()->id,
        ]);
    }

    /**
     * Indicate that the model should have a Student role.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('name', 'Student')->first()->id,
        ]);
    }
}
